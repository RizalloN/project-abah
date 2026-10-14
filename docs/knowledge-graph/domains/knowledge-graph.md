# Domain: knowledge-graph

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=knowledge-graph --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 1 |
| file | 5 |
| method | 95 |
| class | 5 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `PhpSourceAnalyzer` | class | 43 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:7` |
| `RepositoryKnowledgeGraphBuilder` | class | 40 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:12` |
| `KnowledgeGraph` | class | 24 | `app/Support/KnowledgeGraph/KnowledgeGraph.php:5` |
| `analyze` | method | 21 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:40` |
| `addNode` | method | 16 | `app/Support/KnowledgeGraph/KnowledgeGraph.php:16` |
| `analyzeFunctionBody` | method | 16 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:477` |
| `addEdge` | method | 15 | `app/Support/KnowledgeGraph/KnowledgeGraph.php:46` |
| `build` | method | 14 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:72` |
| `KnowledgeGraphIndex` | class | 13 | `app/Support/KnowledgeGraph/KnowledgeGraphIndex.php:7` |
| `write` | method | 13 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:117` |
| `analyzeTables` | method | 12 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:600` |
| `analyzeRuntimeRoutes` | method | 11 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:406` |
| `sourceFiles` | method | 11 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:225` |
| `addClassReference` | method | 10 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:958` |
| `addTableReference` | method | 9 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:708` |
| `addMethodCall` | method | 8 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:926` |
| `analyzeBlade` | method | 8 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:302` |
| `analyzeClassDependencies` | method | 8 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:370` |
| `analyzeOperationalFile` | method | 8 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:357` |
| `analyzeQueues` | method | 8 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:824` |
| `analyzeViewsAndRoutes` | method | 8 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:768` |
| `classDeclarations` | method | 8 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:224` |
| `handle` | method | 8 | `app/Console/Commands/KnowledgeGraphCommand.php:24` |
| `analyzeTraits` | method | 7 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:444` |
| `attachDomains` | method | 7 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:525` |
| `functionDeclarations` | method | 7 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:299` |
| `shortName` | method | 7 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:1076` |
| `writeText` | method | 7 | `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php:963` |
| `KnowledgeGraphCommand` | class | 6 | `app/Console/Commands/KnowledgeGraphCommand.php:11` |
| `analyzeCommand` | method | 6 | `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php:798` |

## Class Nodes

- `KnowledgeGraph` - `app/Support/KnowledgeGraph/KnowledgeGraph.php`
- `KnowledgeGraphCommand` - `app/Console/Commands/KnowledgeGraphCommand.php`
- `KnowledgeGraphIndex` - `app/Support/KnowledgeGraph/KnowledgeGraphIndex.php`
- `PhpSourceAnalyzer` - `app/Support/KnowledgeGraph/PhpSourceAnalyzer.php`
- `RepositoryKnowledgeGraphBuilder` - `app/Support/KnowledgeGraph/RepositoryKnowledgeGraphBuilder.php`

## Command Nodes

- `knowledge:graph` - `app/Console/Commands/KnowledgeGraphCommand.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| knowledge-graph -> core (instantiates) | 6 |
| knowledge-graph -> core (accepts) | 2 |
| knowledge-graph -> core (calls) | 1 |
| knowledge-graph -> core (injects) | 1 |
| knowledge-graph -> core (extends) | 1 |
