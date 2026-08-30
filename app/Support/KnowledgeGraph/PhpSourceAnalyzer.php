<?php

namespace App\Support\KnowledgeGraph;

use Closure;

final class PhpSourceAnalyzer
{
    /** @var array<int, string> */
    private const BUILTIN_TYPES = [
        'array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'mixed',
        'never', 'null', 'object', 'resource', 'self', 'static', 'string', 'true',
        'void',
    ];

    /** @var array<int, string> */
    private const STATIC_CALLS_WITH_DEDICATED_EDGES = [
        'DB', 'Schema', 'Route', 'View', 'Cache', 'Log', 'Storage', 'Config',
    ];

    /** @param Closure(string): string $domainResolver */
    public function __construct(private readonly Closure $domainResolver) {}

    public function analyze(string $relativePath, string $source, KnowledgeGraph $graph): void
    {
        $tokens = $this->tokenize($source);
        $namespace = $this->namespace($tokens);
        $imports = $this->imports($tokens);
        $fileId = 'file:'.$relativePath;
        $classes = $this->classDeclarations($tokens, $namespace, $imports);

        foreach ($classes as $class) {
            $classId = 'symbol:'.$class['fqn'];
            $graph->addNode($classId, $class['kind'], $class['name'], [
                'fqn' => $class['fqn'],
                'path' => $relativePath,
                'line' => $class['line'],
                'end_line' => $class['end_line'],
                'domain' => ($this->domainResolver)($relativePath.' '.$class['fqn']),
                'role' => $this->symbolRole($class['fqn'], $relativePath),
            ]);
            $graph->addEdge($fileId, 'contains', $classId, [
                'path' => $relativePath,
                'line' => $class['line'],
            ]);

            foreach ($class['extends'] as $target) {
                $targetFqn = $this->resolveName($target, $namespace, $imports, $class['fqn']);
                $this->addClassReference($graph, $targetFqn, $relativePath, $classId, 'extends', $class['line']);
            }
            foreach ($class['implements'] as $target) {
                $targetFqn = $this->resolveName($target, $namespace, $imports, $class['fqn']);
                $this->addClassReference($graph, $targetFqn, $relativePath, $classId, 'implements', $class['line']);
            }
        }

        $functions = $this->functionDeclarations($tokens, $classes, $namespace);
        $methodLookup = [];
        foreach ($functions as &$function) {
            $owner = $function['class_fqn'] ?? null;
            $functionFqn = $owner
                ? $owner.'::'.$function['name']
                : ($namespace !== ''
                    ? $namespace.'\\'.$function['name']
                    : str_replace('/', '\\', $relativePath).'::'.$function['name']);
            $id = $owner
                ? 'method:'.$owner.'::'.$function['name']
                : 'function:'.$functionFqn;
            $kind = $owner ? 'method' : 'function';
            $domainText = $relativePath.' '.($owner ?: $namespace).' '.$function['name'];
            $function['id'] = $id;
            $methodLookup[$id] = true;

            $graph->addNode($id, $kind, $function['name'], [
                'fqn' => $functionFqn,
                'path' => $relativePath,
                'line' => $function['line'],
                'end_line' => $function['end_line'],
                'visibility' => $function['visibility'],
                'domain' => ($this->domainResolver)($domainText),
            ]);
            $graph->addEdge($owner ? 'symbol:'.$owner : $fileId, 'contains', $id, [
                'path' => $relativePath,
                'line' => $function['line'],
            ]);
        }
        unset($function);

        foreach ($classes as $class) {
            $this->analyzeClassDependencies($class, $functions, $tokens, $source, $namespace, $imports, $relativePath, $graph);
            $this->analyzeTraits($class, $tokens, $namespace, $imports, $relativePath, $graph);
        }

        foreach ($functions as $function) {
            $this->analyzeFunctionBody($function, $classes, $source, $namespace, $imports, $relativePath, $graph);
        }

        $this->analyzeTables($source, $tokens, $functions, $classes, $relativePath, $graph);
        $this->analyzeViewsAndRoutes($source, $functions, $classes, $relativePath, $graph);
        $this->analyzeCommand($source, $classes, $methodLookup, $relativePath, $graph);
        $this->analyzeQueues($source, $functions, $classes, $relativePath, $graph);
    }

    /** @return array<int, array<string, mixed>> */
    private function tokenize(string $source): array
    {
        $tokens = [];
        $offset = 0;
        $line = 1;

        foreach (token_get_all($source) as $rawToken) {
            if (is_array($rawToken)) {
                [$id, $text, $tokenLine] = $rawToken;
                $line = $tokenLine;
            } else {
                $id = null;
                $text = $rawToken;
            }

            $tokens[] = [
                'id' => $id,
                'text' => $text,
                'line' => $line,
                'offset' => $offset,
                'end_offset' => $offset + strlen($text),
            ];
            $offset += strlen($text);
            $line += substr_count($text, "\n");
        }

        return $tokens;
    }

    /** @param array<int, array<string, mixed>> $tokens */
    private function namespace(array $tokens): string
    {
        foreach ($tokens as $index => $token) {
            if ($token['id'] !== T_NAMESPACE) {
                continue;
            }

            return trim($this->collectName($tokens, $index + 1, [';', '{']), " \t\n\r\0\x0B\\");
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @return array<string, string>
     */
    private function imports(array $tokens): array
    {
        $imports = [];
        $depth = 0;

        foreach ($tokens as $index => $token) {
            if ($token['text'] === '{') {
                $depth++;

                continue;
            }
            if ($token['text'] === '}') {
                $depth = max(0, $depth - 1);

                continue;
            }
            if ($token['id'] !== T_USE || $depth !== 0) {
                continue;
            }

            $statement = trim($this->collectName($tokens, $index + 1, [';']));
            if ($statement === '' || str_starts_with($statement, 'function ') || str_starts_with($statement, 'const ')) {
                continue;
            }

            foreach ($this->splitTopLevel($statement, ',') as $import) {
                $import = trim($import);
                if ($import === '' || str_contains($import, '{')) {
                    continue;
                }

                if (preg_match('/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $import, $matches) === 1) {
                    $fqn = trim($matches[1], " \t\n\r\0\x0B\\");
                    $alias = $matches[2];
                } else {
                    $fqn = trim($import, " \t\n\r\0\x0B\\");
                    $parts = explode('\\', $fqn);
                    $alias = (string) end($parts);
                }

                if ($alias !== '' && $fqn !== '') {
                    $imports[$alias] = $fqn;
                }
            }
        }

        return $imports;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<string, string>  $imports
     * @return array<int, array<string, mixed>>
     */
    private function classDeclarations(array $tokens, string $namespace, array $imports): array
    {
        $classes = [];
        $classTokenIds = array_filter([
            T_CLASS,
            T_INTERFACE,
            T_TRAIT,
            defined('T_ENUM') ? constant('T_ENUM') : null,
        ]);

        foreach ($tokens as $index => $token) {
            if (! in_array($token['id'], $classTokenIds, true)) {
                continue;
            }

            $previous = $this->previousMeaningful($tokens, $index - 1);
            if (in_array($previous['id'] ?? null, [T_NEW, T_DOUBLE_COLON], true)) {
                continue;
            }

            $nameIndex = $this->nextTokenWithId($tokens, $index + 1, T_STRING);
            if ($nameIndex === null) {
                continue;
            }

            $bodyStart = $this->nextTokenWithText($tokens, $nameIndex + 1, '{');
            if ($bodyStart === null) {
                continue;
            }

            $bodyEnd = $this->matchingBrace($tokens, $bodyStart);
            $header = $this->tokensText($tokens, $nameIndex + 1, $bodyStart - 1);
            $name = $tokens[$nameIndex]['text'];
            $fqn = ltrim($namespace.'\\'.$name, '\\');
            $kind = match ($token['id']) {
                T_INTERFACE => 'interface',
                T_TRAIT => 'trait',
                default => defined('T_ENUM') && $token['id'] === constant('T_ENUM') ? 'enum' : 'class',
            };

            $extends = [];
            if (preg_match('/\bextends\s+([^\{]+?)(?=\bimplements\b|$)/i', $header, $matches) === 1) {
                $extends = array_values(array_filter(array_map('trim', explode(',', $matches[1]))));
            }
            $implements = [];
            if (preg_match('/\bimplements\s+(.+)$/i', $header, $matches) === 1) {
                $implements = array_values(array_filter(array_map('trim', explode(',', $matches[1]))));
            }

            $classes[] = [
                'name' => $name,
                'fqn' => $fqn,
                'kind' => $kind,
                'start_token' => $index,
                'body_start_token' => $bodyStart,
                'end_token' => $bodyEnd,
                'start_offset' => $token['offset'],
                'body_offset' => $tokens[$bodyStart]['offset'],
                'end_offset' => $tokens[$bodyEnd]['end_offset'],
                'line' => $token['line'],
                'end_line' => $tokens[$bodyEnd]['line'],
                'extends' => $extends,
                'implements' => $implements,
                'imports' => $imports,
            ];
        }

        return $classes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<int, array<string, mixed>>  $classes
     * @return array<int, array<string, mixed>>
     */
    private function functionDeclarations(array $tokens, array $classes, string $namespace): array
    {
        $functions = [];

        foreach ($tokens as $index => $token) {
            if ($token['id'] !== T_FUNCTION) {
                continue;
            }

            $nameIndex = $this->nextMeaningfulIndex($tokens, $index + 1);
            if ($nameIndex !== null && $tokens[$nameIndex]['text'] === '&') {
                $nameIndex = $this->nextMeaningfulIndex($tokens, $nameIndex + 1);
            }
            if ($nameIndex === null || $tokens[$nameIndex]['id'] !== T_STRING) {
                continue;
            }

            $bodyStart = null;
            $declarationEnd = $nameIndex;
            $parentheses = 0;
            for ($cursor = $nameIndex + 1, $count = count($tokens); $cursor < $count; $cursor++) {
                $text = $tokens[$cursor]['text'];
                if ($text === '(') {
                    $parentheses++;
                } elseif ($text === ')') {
                    $parentheses = max(0, $parentheses - 1);
                } elseif ($parentheses === 0 && ($text === '{' || $text === ';')) {
                    $declarationEnd = $cursor;
                    $bodyStart = $text === '{' ? $cursor : null;
                    break;
                }
            }

            $bodyEnd = $bodyStart !== null ? $this->matchingBrace($tokens, $bodyStart) : $declarationEnd;
            $owner = $this->ownerClass($classes, $index);
            $visibility = 'public';
            $modifiers = $this->tokensText($tokens, max(0, $index - 8), $index - 1);
            if (preg_match('/\b(private|protected|public)\b/i', $modifiers, $matches) === 1) {
                $visibility = strtolower($matches[1]);
            }

            $functions[] = [
                'name' => $tokens[$nameIndex]['text'],
                'class_fqn' => $owner['fqn'] ?? null,
                'start_token' => $index,
                'body_start_token' => $bodyStart,
                'end_token' => $bodyEnd,
                'start_offset' => $token['offset'],
                'body_offset' => $bodyStart !== null ? $tokens[$bodyStart]['offset'] : $tokens[$declarationEnd]['offset'],
                'end_offset' => $tokens[$bodyEnd]['end_offset'],
                'line' => $token['line'],
                'end_line' => $tokens[$bodyEnd]['line'],
                'visibility' => $visibility,
                'namespace' => $namespace,
            ];
        }

        return $functions;
    }

    /**
     * @param  array<string, mixed>  $class
     * @param  array<int, array<string, mixed>>  $functions
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<string, string>  $imports
     */
    private function analyzeClassDependencies(
        array $class,
        array $functions,
        array $tokens,
        string $source,
        string $namespace,
        array $imports,
        string $relativePath,
        KnowledgeGraph $graph
    ): void {
        $classId = 'symbol:'.$class['fqn'];
        $classSource = substr($source, $class['body_offset'], $class['end_offset'] - $class['body_offset']);
        $propertyTypes = [];

        if (preg_match_all(
            '/\b(?:public|protected|private)\s+(?:static\s+)?(?:readonly\s+)?(?<type>\??[\\\\A-Za-z_][\\\\A-Za-z0-9_|&?]*)\s+\$(?<name>[A-Za-z_][A-Za-z0-9_]*)/',
            $classSource,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $type = $this->firstClassType($match['type'], $namespace, $imports, $class['fqn']);
                if ($type !== null) {
                    $propertyTypes[$match['name']] = $type;
                }
            }
        }

        foreach ($functions as $function) {
            if (($function['class_fqn'] ?? null) !== $class['fqn']) {
                continue;
            }

            $signature = substr($source, $function['start_offset'], max(0, $function['body_offset'] - $function['start_offset']));
            $parameterTypes = $this->typedVariables($signature, $namespace, $imports, $class['fqn']);
            foreach ($parameterTypes as $variable => $type) {
                $relation = $function['name'] === '__construct' ? 'injects' : 'accepts';
                $this->addClassReference($graph, $type, $relativePath, $function['id'], $relation, $function['line']);
                if ($function['name'] === '__construct' && preg_match('/\b(?:public|protected|private)\b[^,$)]*\$'.preg_quote($variable, '/').'\b/', $signature) === 1) {
                    $propertyTypes[$variable] = $type;
                }
            }

            if ($function['name'] === '__construct') {
                $body = substr($source, $function['body_offset'], $function['end_offset'] - $function['body_offset']);
                if (preg_match_all('/\$this->(?<property>[A-Za-z_]\w*)\s*=\s*\$(?<variable>[A-Za-z_]\w*)/', $body, $assignments, PREG_SET_ORDER)) {
                    foreach ($assignments as $assignment) {
                        if (isset($parameterTypes[$assignment['variable']])) {
                            $propertyTypes[$assignment['property']] = $parameterTypes[$assignment['variable']];
                        }
                    }
                }
            }
        }

        $class['property_types'] = $propertyTypes;
        $graph->addNode($classId, $class['kind'], $class['name'], [
            'dependencies' => array_values(array_unique($propertyTypes)),
        ]);

        // Store inferred property types on method records through a side channel used below.
        foreach ($functions as &$function) {
            if (($function['class_fqn'] ?? null) === $class['fqn']) {
                $function['property_types'] = $propertyTypes;
            }
        }
        unset($function);
    }

    /**
     * @param  array<string, mixed>  $class
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<string, string>  $imports
     */
    private function analyzeTraits(array $class, array $tokens, string $namespace, array $imports, string $relativePath, KnowledgeGraph $graph): void
    {
        $depth = 0;
        for ($index = $class['body_start_token'] + 1; $index < $class['end_token']; $index++) {
            $token = $tokens[$index];
            if ($token['text'] === '{') {
                $depth++;

                continue;
            }
            if ($token['text'] === '}') {
                $depth = max(0, $depth - 1);

                continue;
            }
            if ($token['id'] !== T_USE || $depth !== 0) {
                continue;
            }

            $statement = trim($this->collectName($tokens, $index + 1, [';', '{']));
            foreach (array_filter(array_map('trim', explode(',', $statement))) as $trait) {
                $target = $this->resolveName($trait, $namespace, $imports, $class['fqn']);
                $this->addClassReference($graph, $target, $relativePath, 'symbol:'.$class['fqn'], 'uses_trait', $token['line']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $function
     * @param  array<int, array<string, mixed>>  $classes
     * @param  array<string, string>  $imports
     */
    private function analyzeFunctionBody(
        array $function,
        array $classes,
        string $source,
        string $namespace,
        array $imports,
        string $relativePath,
        KnowledgeGraph $graph
    ): void {
        if ($function['body_start_token'] === null) {
            return;
        }

        $body = substr($source, $function['body_offset'], $function['end_offset'] - $function['body_offset']);
        $owner = $function['class_fqn'] ?? null;
        $ownerClass = $owner ? $this->classByFqn($classes, $owner) : null;
        $signature = substr($source, $function['start_offset'], max(0, $function['body_offset'] - $function['start_offset']));
        $variableTypes = $this->typedVariables($signature, $namespace, $imports, $owner);
        $propertyTypes = $this->propertyTypesForClass($source, $ownerClass, $namespace, $imports);

        if (preg_match_all('/\$this->(?<property>[A-Za-z_]\w*)->(?<method>[A-Za-z_]\w*)\s*\(/', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $property = $match['property'][0];
                if (! isset($propertyTypes[$property])) {
                    continue;
                }
                $this->addMethodCall($graph, $function['id'], $propertyTypes[$property], $match['method'][0], $relativePath, $this->lineAt($source, $function['body_offset'] + $match[0][1]));
            }
        }

        $declaredMethods = $owner
            ? array_column(array_filter($this->functionsForOwner($owner, $source), static fn (array $item): bool => isset($item['name'])), 'name')
            : [];
        if ($owner && preg_match_all('/\$this->(?<method>[A-Za-z_]\w*)\s*\(/', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                if (! in_array($match['method'][0], $declaredMethods, true)) {
                    continue;
                }
                $this->addMethodCall($graph, $function['id'], $owner, $match['method'][0], $relativePath, $this->lineAt($source, $function['body_offset'] + $match[0][1]));
            }
        }

        if (preg_match_all('/\$(?<variable>[A-Za-z_]\w*)->(?<method>[A-Za-z_]\w*)\s*\(/', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $variable = $match['variable'][0];
                if ($variable === 'this' || ! isset($variableTypes[$variable])) {
                    continue;
                }
                $this->addMethodCall($graph, $function['id'], $variableTypes[$variable], $match['method'][0], $relativePath, $this->lineAt($source, $function['body_offset'] + $match[0][1]));
            }
        }

        if (preg_match_all('/(?<![$>A-Za-z0-9_])(?<class>\\\\?[A-Za-z_][\\\\A-Za-z0-9_]*)::(?<method>[A-Za-z_]\w*)\s*\(/', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $className = $match['class'][0];
                $short = basename(str_replace('\\', '/', $className));
                if (in_array($short, self::STATIC_CALLS_WITH_DEDICATED_EDGES, true)) {
                    continue;
                }
                $targetClass = $this->resolveName($className, $namespace, $imports, $owner);
                $relation = $match['method'][0] === 'dispatch' ? 'dispatches' : 'calls';
                $this->addMethodCall($graph, $function['id'], $targetClass, $match['method'][0], $relativePath, $this->lineAt($source, $function['body_offset'] + $match[0][1]), $relation);
            }
        }

        if (preg_match_all('/\bnew\s+(?<class>\\\\?[A-Za-z_][\\\\A-Za-z0-9_]*)/', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $target = $this->resolveName($match['class'][0], $namespace, $imports, $owner);
                $this->addClassReference($graph, $target, $relativePath, $function['id'], 'instantiates', $this->lineAt($source, $function['body_offset'] + $match[0][1]));
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<int, array<string, mixed>>  $functions
     * @param  array<int, array<string, mixed>>  $classes
     */
    private function analyzeTables(string $source, array $tokens, array $functions, array $classes, string $relativePath, KnowledgeGraph $graph): void
    {
        $staticMethods = [
            'schema' => [
                'create' => 'defines_table',
                'table' => 'alters_table',
                'hastable' => 'checks_table',
            ],
            'db' => [
                'table' => 'uses_table',
            ],
        ];

        foreach ($tokens as $index => $token) {
            if (! in_array($token['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $facade = strtolower($this->shortName(ltrim($token['text'], '\\')));
            if (! isset($staticMethods[$facade])) {
                continue;
            }

            $scopeIndex = $this->nextMeaningfulIndex($tokens, $index + 1);
            $methodIndex = $scopeIndex !== null ? $this->nextMeaningfulIndex($tokens, $scopeIndex + 1) : null;
            $openIndex = $methodIndex !== null ? $this->nextMeaningfulIndex($tokens, $methodIndex + 1) : null;
            $literalIndex = $openIndex !== null ? $this->nextMeaningfulIndex($tokens, $openIndex + 1) : null;
            if ($scopeIndex === null || $tokens[$scopeIndex]['text'] !== '::'
                || $methodIndex === null || $tokens[$methodIndex]['id'] !== T_STRING
                || $openIndex === null || $tokens[$openIndex]['text'] !== '('
                || $literalIndex === null || $tokens[$literalIndex]['id'] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $method = strtolower($tokens[$methodIndex]['text']);
            $relation = $staticMethods[$facade][$method] ?? null;
            if ($relation === null) {
                continue;
            }

            $this->addTableReference(
                $graph,
                $source,
                $functions,
                $classes,
                $relativePath,
                $this->literalStringValue($tokens[$literalIndex]['text']),
                $relation,
                $token['offset']
            );
        }

        $joinMethods = ['leftjoin', 'rightjoin', 'join', 'from'];
        foreach ($tokens as $index => $token) {
            if ($token['id'] !== T_OBJECT_OPERATOR) {
                continue;
            }

            $methodIndex = $this->nextMeaningfulIndex($tokens, $index + 1);
            $openIndex = $methodIndex !== null ? $this->nextMeaningfulIndex($tokens, $methodIndex + 1) : null;
            $literalIndex = $openIndex !== null ? $this->nextMeaningfulIndex($tokens, $openIndex + 1) : null;
            if ($methodIndex === null || $tokens[$methodIndex]['id'] !== T_STRING
                || ! in_array(strtolower($tokens[$methodIndex]['text']), $joinMethods, true)
                || $openIndex === null || $tokens[$openIndex]['text'] !== '('
                || $literalIndex === null || $tokens[$literalIndex]['id'] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $this->addTableReference(
                $graph,
                $source,
                $functions,
                $classes,
                $relativePath,
                $this->literalStringValue($tokens[$literalIndex]['text']),
                'joins_table',
                $token['offset']
            );
        }

        foreach ($tokens as $index => $token) {
            if ($token['id'] !== T_VARIABLE || $token['text'] !== '$table') {
                continue;
            }

            $equalsIndex = $this->nextMeaningfulIndex($tokens, $index + 1);
            $literalIndex = $equalsIndex !== null ? $this->nextMeaningfulIndex($tokens, $equalsIndex + 1) : null;
            $class = $this->ownerClass($classes, $index);
            if ($class === null || $equalsIndex === null || $tokens[$equalsIndex]['text'] !== '='
                || $literalIndex === null || $tokens[$literalIndex]['id'] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $table = $this->normalizedTableName($this->literalStringValue($tokens[$literalIndex]['text']));
            if ($table === null) {
                continue;
            }

            $tableId = 'table:'.strtolower($table);
            $graph->addNode($tableId, 'table', $table, ['domain' => ($this->domainResolver)($table)]);
            $graph->addEdge('symbol:'.$class['fqn'], 'maps_table', $tableId, [
                'path' => $relativePath,
                'line' => $token['line'],
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $functions @param array<int, array<string, mixed>> $classes */
    private function addTableReference(
        KnowledgeGraph $graph,
        string $source,
        array $functions,
        array $classes,
        string $relativePath,
        string $rawTable,
        string $relation,
        int $offset
    ): void {
        $table = $this->normalizedTableName($rawTable);
        if ($table === null) {
            return;
        }

        if ($relation === 'uses_table') {
            $statement = substr($source, $offset, 1200);
            $statementEnd = strpos($statement, ';');
            if ($statementEnd !== false) {
                $statement = substr($statement, 0, $statementEnd + 1);
            }
            $relation = preg_match('/->(?:insert|insertGetId|upsert|update|updateOrInsert|delete|truncate)\s*\(/', $statement) === 1
                ? 'writes_table'
                : 'reads_table';
        }

        $tableId = 'table:'.strtolower($table);
        $graph->addNode($tableId, 'table', $table, ['domain' => ($this->domainResolver)($table)]);
        $graph->addEdge(
            $this->sourceAtOffset($offset, $functions, $classes, $relativePath),
            $relation,
            $tableId,
            ['path' => $relativePath, 'line' => $this->lineAt($source, $offset)]
        );
    }

    private function normalizedTableName(string $rawTable): ?string
    {
        $table = preg_split('/\s+(?:as\s+)?/i', trim($rawTable))[0] ?? $rawTable;
        $table = trim(str_replace(chr(96), '', $table), "[] \t\n\r\0\x0B");

        return preg_match('/^[A-Za-z_][A-Za-z0-9_$]*(?:\.[A-Za-z_][A-Za-z0-9_$]*)?$/', $table) === 1
            ? $table
            : null;
    }

    private function literalStringValue(string $literal): string
    {
        if (strlen($literal) < 2 || ! in_array($literal[0], ["'", '"'], true) || substr($literal, -1) !== $literal[0]) {
            return '';
        }

        $value = substr($literal, 1, -1);

        return $literal[0] === "'"
            ? str_replace(['\\\\', "\\'"], ['\\', "'"], $value)
            : stripcslashes($value);
    }

    /** @param array<int, array<string, mixed>> $functions @param array<int, array<string, mixed>> $classes */
    private function analyzeViewsAndRoutes(string $source, array $functions, array $classes, string $relativePath, KnowledgeGraph $graph): void
    {
        $patterns = [
            'renders' => '/(?<![A-Za-z_])(?:view|View::make|response\(\)->view)\(\s*[\'\"](?<name>[A-Za-z0-9_.\/-]+)[\'\"]/',
            'references_route' => '/(?<![A-Za-z_])route\(\s*[\'\"](?<name>[A-Za-z0-9_.-]+)[\'\"]/',
        ];

        foreach ($patterns as $relation => $pattern) {
            if (! preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches as $match) {
                $name = $match['name'][0];
                $offset = $match[0][1];
                $sourceId = $this->sourceAtOffset($offset, $functions, $classes, $relativePath);
                $targetId = $relation === 'renders' ? 'view:'.$name : 'route:'.$name;
                $targetKind = $relation === 'renders' ? 'view' : 'route';
                $graph->addNode($targetId, $targetKind, $name, [
                    'domain' => ($this->domainResolver)($name),
                    'status' => 'referenced',
                ]);
                $graph->addEdge($sourceId, $relation, $targetId, [
                    'path' => $relativePath,
                    'line' => $this->lineAt($source, $offset),
                ]);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $classes @param array<string, bool> $methodLookup */
    private function analyzeCommand(string $source, array $classes, array $methodLookup, string $relativePath, KnowledgeGraph $graph): void
    {
        if ($classes === [] || preg_match('/protected\s+\$signature\s*=\s*([\'\"])(?<signature>.*?)\1\s*;/s', $source, $match) !== 1) {
            return;
        }

        $signature = preg_replace('/\s+/', ' ', trim($match['signature'])) ?? trim($match['signature']);
        $commandName = preg_split('/\s+/', $signature)[0] ?? '';
        if ($commandName === '') {
            return;
        }

        $class = $classes[0];
        $commandId = 'command:'.$commandName;
        $handleId = 'method:'.$class['fqn'].'::handle';
        $graph->addNode($commandId, 'command', $commandName, [
            'signature' => $signature,
            'path' => $relativePath,
            'domain' => ($this->domainResolver)($relativePath.' '.$commandName),
        ]);
        $graph->addEdge($commandId, 'handled_by', isset($methodLookup[$handleId]) ? $handleId : 'symbol:'.$class['fqn'], [
            'path' => $relativePath,
        ]);
    }

    /** @param array<int, array<string, mixed>> $functions @param array<int, array<string, mixed>> $classes */
    private function analyzeQueues(string $source, array $functions, array $classes, string $relativePath, KnowledgeGraph $graph): void
    {
        if (! preg_match_all('/(?:onQueue\(|\$queue\s*=\s*)[\'\"](?<queue>[A-Za-z0-9_.-]+)[\'\"]/', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($matches as $match) {
            $queue = $match['queue'][0];
            $offset = $match[0][1];
            $queueId = 'queue:'.$queue;
            $graph->addNode($queueId, 'queue', $queue, ['domain' => 'jobs-snapshots']);
            $graph->addEdge(
                $this->sourceAtOffset($offset, $functions, $classes, $relativePath),
                'uses_queue',
                $queueId,
                ['path' => $relativePath, 'line' => $this->lineAt($source, $offset)]
            );
        }
    }

    /** @return array<string, string> */
    private function propertyTypesForClass(string $source, ?array $class, string $namespace, array $imports): array
    {
        if ($class === null) {
            return [];
        }

        $classSource = substr($source, $class['body_offset'], $class['end_offset'] - $class['body_offset']);
        $types = [];
        if (preg_match_all(
            '/\b(?:public|protected|private)\s+(?:static\s+)?(?:readonly\s+)?(?<type>\??[\\\\A-Za-z_][\\\\A-Za-z0-9_|&?]*)\s+\$(?<name>[A-Za-z_]\w*)/',
            $classSource,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $type = $this->firstClassType($match['type'], $namespace, $imports, $class['fqn']);
                if ($type !== null) {
                    $types[$match['name']] = $type;
                }
            }
        }

        $constructors = [];
        if (preg_match_all('/function\s+__construct\s*\((?<params>.*?)\)\s*\{(?<body>.*?)\n\s*\}/s', $classSource, $constructors, PREG_SET_ORDER)) {
            foreach ($constructors as $constructor) {
                $variables = $this->typedVariables($constructor['params'], $namespace, $imports, $class['fqn']);
                foreach ($variables as $name => $type) {
                    if (preg_match('/\b(?:public|protected|private)\b[^,$)]*\$'.preg_quote($name, '/').'\b/', $constructor['params']) === 1) {
                        $types[$name] = $type;
                    }
                }
                if (preg_match_all('/\$this->(?<property>[A-Za-z_]\w*)\s*=\s*\$(?<variable>[A-Za-z_]\w*)/', $constructor['body'], $assignments, PREG_SET_ORDER)) {
                    foreach ($assignments as $assignment) {
                        if (isset($variables[$assignment['variable']])) {
                            $types[$assignment['property']] = $variables[$assignment['variable']];
                        }
                    }
                }
            }
        }

        return $types;
    }

    /** @return array<string, string> */
    private function typedVariables(string $signature, string $namespace, array $imports, ?string $owner): array
    {
        $variables = [];
        if (! preg_match_all(
            '/(?<type>\??[\\\\A-Za-z_][\\\\A-Za-z0-9_|&?]*)\s+\$(?<name>[A-Za-z_][A-Za-z0-9_]*)/',
            $signature,
            $matches,
            PREG_SET_ORDER
        )) {
            return $variables;
        }

        foreach ($matches as $match) {
            $type = $this->firstClassType($match['type'], $namespace, $imports, $owner);
            if ($type !== null) {
                $variables[$match['name']] = $type;
            }
        }

        return $variables;
    }

    private function firstClassType(string $type, string $namespace, array $imports, ?string $owner): ?string
    {
        foreach (preg_split('/[|&]/', trim($type, '?')) ?: [] as $candidate) {
            $candidate = trim($candidate, " ?\t\n\r\0\x0B");
            if ($candidate === '' || in_array(strtolower($candidate), self::BUILTIN_TYPES, true)) {
                continue;
            }

            return $this->resolveName($candidate, $namespace, $imports, $owner);
        }

        return null;
    }

    private function addMethodCall(KnowledgeGraph $graph, string $sourceId, string $classFqn, string $method, string $path, int $line, string $relation = 'calls'): void
    {
        if ($classFqn === '' || $method === '') {
            return;
        }

        $classId = 'symbol:'.$classFqn;
        $methodId = 'method:'.$classFqn.'::'.$method;
        $graph->addNode($classId, 'unresolved_symbol', $this->shortName($classFqn), [
            'fqn' => $classFqn,
            'domain' => ($this->domainResolver)($classFqn),
            'status' => str_starts_with($classFqn, 'App\\') ? 'unresolved' : 'external',
        ]);
        $graph->addNode($methodId, 'unresolved_symbol', $method, [
            'fqn' => $classFqn.'::'.$method,
            'domain' => ($this->domainResolver)($classFqn),
            'status' => str_starts_with($classFqn, 'App\\') ? 'unresolved' : 'external',
        ]);
        $graph->addEdge($classId, 'contains', $methodId);
        $graph->addEdge($sourceId, $relation, $methodId, ['path' => $path, 'line' => $line]);
    }

    private function addClassReference(KnowledgeGraph $graph, string $targetFqn, string $path, string $sourceId, string $relation, int $line): void
    {
        if ($targetFqn === '') {
            return;
        }

        $targetId = 'symbol:'.$targetFqn;
        $graph->addNode($targetId, 'unresolved_symbol', $this->shortName($targetFqn), [
            'fqn' => $targetFqn,
            'domain' => ($this->domainResolver)($targetFqn),
            'status' => str_starts_with($targetFqn, 'App\\') ? 'unresolved' : 'external',
        ]);
        $graph->addEdge($sourceId, $relation, $targetId, ['path' => $path, 'line' => $line]);
    }

    private function sourceAtOffset(int $offset, array $functions, array $classes, string $relativePath): string
    {
        foreach ($functions as $function) {
            if ($offset >= $function['start_offset'] && $offset <= $function['end_offset']) {
                return $function['id'];
            }
        }
        foreach ($classes as $class) {
            if ($offset >= $class['start_offset'] && $offset <= $class['end_offset']) {
                return 'symbol:'.$class['fqn'];
            }
        }

        return 'file:'.$relativePath;
    }

    private function resolveName(string $name, string $namespace, array $imports, ?string $owner = null): string
    {
        $name = preg_replace('/\s+/', '', trim($name)) ?? trim($name);
        $fullyQualified = str_starts_with($name, '\\');
        $name = ltrim($name, '\\');
        if ($name === '') {
            return '';
        }
        if ($fullyQualified) {
            return $name;
        }
        if (in_array(strtolower($name), ['self', 'static'], true) && $owner) {
            return $owner;
        }
        if (strtolower($name) === 'parent' && $owner) {
            return $owner;
        }

        $parts = explode('\\', $name);
        $first = $parts[0];
        if (isset($imports[$first])) {
            array_shift($parts);

            return $imports[$first].($parts !== [] ? '\\'.implode('\\', $parts) : '');
        }
        if (str_contains($name, '\\') && str_starts_with($name, 'App\\')) {
            return $name;
        }

        return ltrim($namespace.'\\'.$name, '\\');
    }

    /** @return array<int, array{name: string}> */
    private function functionsForOwner(string $owner, string $source): array
    {
        $methods = [];
        $shortName = preg_quote($this->shortName($owner), '/');
        if (preg_match('/\b(?:class|interface|trait|enum)\s+'.$shortName.'\b[^\{]*\{(?<body>.*)\}\s*$/s', $source, $classMatch) !== 1) {
            return [];
        }
        if (preg_match_all('/\bfunction\s+&?\s*(?<name>[A-Za-z_]\w*)\s*\(/', $classMatch['body'], $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $methods[] = ['name' => $match['name']];
            }
        }

        return $methods;
    }

    private function symbolRole(string $fqn, string $path): string
    {
        $text = strtolower($fqn.' '.$path);
        $roles = [
            'controller' => ['controller'],
            'middleware' => ['middleware'],
            'command' => ['console\\commands', '/console/commands/'],
            'job' => ['app\\jobs', '/jobs/'],
            'model' => ['app\\models', '/models/'],
            'service' => ['service'],
            'request' => ['request'],
            'provider' => ['provider'],
            'notification' => ['notification'],
            'test' => ['tests\\', '/tests/', 'test.php'],
        ];
        foreach ($roles as $role => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $role;
                }
            }
        }

        return 'support';
    }

    private function lineAt(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, max(0, $offset)), "\n") + 1;
    }

    private function shortName(string $fqn): string
    {
        $parts = explode('\\', $fqn);

        return (string) end($parts);
    }

    private function classByFqn(array $classes, string $fqn): ?array
    {
        foreach ($classes as $class) {
            if ($class['fqn'] === $fqn) {
                return $class;
            }
        }

        return null;
    }

    private function ownerClass(array $classes, int $tokenIndex): ?array
    {
        $owner = null;
        foreach ($classes as $class) {
            if ($tokenIndex > $class['body_start_token'] && $tokenIndex < $class['end_token']) {
                if ($owner === null || $class['body_start_token'] > $owner['body_start_token']) {
                    $owner = $class;
                }
            }
        }

        return $owner;
    }

    private function matchingBrace(array $tokens, int $start): int
    {
        $depth = 0;
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index]['text'] === '{') {
                $depth++;
            } elseif ($tokens[$index]['text'] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return count($tokens) - 1;
    }

    private function nextTokenWithId(array $tokens, int $start, int $tokenId): ?int
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index]['id'] === $tokenId) {
                return $index;
            }
            if (in_array($tokens[$index]['text'], ['{', ';'], true)) {
                return null;
            }
        }

        return null;
    }

    private function nextTokenWithText(array $tokens, int $start, string $text): ?int
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index]['text'] === $text) {
                return $index;
            }
            if ($tokens[$index]['text'] === ';') {
                return null;
            }
        }

        return null;
    }

    private function nextMeaningfulIndex(array $tokens, int $start): ?int
    {
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            if (! $this->isIgnorable($tokens[$index])) {
                return $index;
            }
        }

        return null;
    }

    private function previousMeaningful(array $tokens, int $start): ?array
    {
        for ($index = $start; $index >= 0; $index--) {
            if (! $this->isIgnorable($tokens[$index])) {
                return $tokens[$index];
            }
        }

        return null;
    }

    private function isIgnorable(array $token): bool
    {
        return in_array($token['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }

    private function collectName(array $tokens, int $start, array $terminators): string
    {
        $value = '';
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            if (in_array($tokens[$index]['text'], $terminators, true)) {
                break;
            }
            $value .= $tokens[$index]['text'];
        }

        return trim($value);
    }

    private function tokensText(array $tokens, int $start, int $end): string
    {
        $text = '';
        for ($index = max(0, $start), $count = count($tokens); $index <= $end && $index < $count; $index++) {
            $text .= $tokens[$index]['text'];
        }

        return $text;
    }

    /** @return array<int, string> */
    private function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        foreach (str_split($value) as $character) {
            if (in_array($character, ['{', '(', '['], true)) {
                $depth++;
            } elseif (in_array($character, ['}', ')', ']'], true)) {
                $depth = max(0, $depth - 1);
            }
            if ($character === $separator && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';

                continue;
            }
            $buffer .= $character;
        }
        $parts[] = $buffer;

        return $parts;
    }
}
