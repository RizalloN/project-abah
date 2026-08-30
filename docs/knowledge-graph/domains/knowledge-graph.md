# Domain: knowledge-graph

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=knowledge-graph --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 7 |
| method | 99 |
| unresolved_symbol | 1 |
| class | 6 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `PhpSourceAnalyzer` | class | 42 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:7` |
| `RepositoryKnowledgeGraphBuilder` | class | 39 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:11` |
| `KnowledgeGraph` | class | 24 | `app/Support/KnowledgeGraph/KnowledgeGraph.php:5` |
| `analyze` | method | 20 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:24` |
| `addNode` | method | 15 | `app/Support/KnowledgeGraph/KnowledgeGraph.php:16` |
| `KnowledgeGraphIndex` | class | 14 | `app/Support/KnowledgeGraph/KnowledgeGraphIndex.php:7` |
| `addEdge` | method | 14 | `app/Support/KnowledgeGraph/KnowledgeGraph.php:46` |
| `build` | method | 14 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:62` |
| `write` | method | 13 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:107` |
| `analyzeFunctionBody` | method | 12 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:453` |
| `analyzeTables` | method | 12 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:531` |
| `RepositoryKnowledgeGraphBuilderTest` | class | 11 | `tests/Unit/RepositoryKnowledgeGraphBuilderTest.php:12` |
| `addClassReference` | method | 10 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:879` |
| `addTableReference` | method | 9 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:639` |
| `sourceFiles` | method | 9 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:215` |
| `analyzeBlade` | method | 8 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:267` |
| `analyzeClassDependencies` | method | 8 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:347` |
| `analyzeOperationalFile` | method | 8 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:322` |
| `analyzeQueues` | method | 8 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:755` |
| `analyzeRuntimeRoutes` | method | 8 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:371` |
| `analyzeViewsAndRoutes` | method | 8 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:699` |
| `classDeclarations` | method | 8 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:206` |
| `handle` | method | 8 | `app/Console/Commands/KnowledgeGraphCommand.php:24` |
| `addMethodCall` | method | 7 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:857` |
| `analyzeTraits` | method | 7 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:421` |
| `attachDomains` | method | 7 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:421` |
| `functionDeclarations` | method | 7 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:281` |
| `writeText` | method | 7 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:859` |
| `KnowledgeGraphCommand` | class | 6 | `app/Console/Commands/KnowledgeGraphCommand.php:11` |
| `analyzeCommand` | method | 6 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:729` |

## Class Nodes

- `KnowledgeGraph` - `app/Support/KnowledgeGraph/KnowledgeGraph.php`
- `KnowledgeGraphCommand` - `app/Console/Commands/KnowledgeGraphCommand.php`
- `KnowledgeGraphIndex` - `app/Support/KnowledgeGraph/KnowledgeGraphIndex.php`
- `PhpSourceAnalyzer` - `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php`
- `RepositoryKnowledgeGraphBuilder` - `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php`
- `RepositoryKnowledgeGraphBuilderTest` - `tests/Unit/RepositoryKnowledgeGraphBuilderTest.php`

## Command Nodes

- `knowledge:graph` - `app/Console/Commands/KnowledgeGraphCommand.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| knowledge-graph -> core (instantiates) | 7 |
| knowledge-graph -> core (accepts) | 2 |
| knowledge-graph -> core (extends) | 2 |
| core -> knowledge-graph (invokes_command) | 1 |
| knowledge-graph -> core (calls) | 1 |
| knowledge-graph -> core (injects) | 1 |
| knowledge-graph -> tests (calls) | 1 |
| knowledge-graph -> core (references_route) | 1 |
| knowledge-graph -> core (renders) | 1 |
