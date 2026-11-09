---
name: rea
description: "Reverse engineer application behaviors, web bundles, Electron/JavaScript apps, .NET assemblies, Android APKs, and native binaries with REA (Reverse Engineer Anything - https://github.com/morluto/rea). Enforces a 3-phase workflow: Decompile -> Understand -> Recreate with strict Evidence tracking (Seen, Inferred, Unknown) to rebuild features and APIs without hallucination."
argument-hint: "<command> [target] [options]"
license: Apache-2.0
metadata:
  author: morluto
  version: "6.7.0"
---

# REA: Reverse Engineer Anything Skill

REA is an agent-facing analysis and reverse-engineering toolchain. It empowers AI coding agents and developers to inspect compiled binaries, mobile applications, web bundles, and runtime behaviors locally without access to original source code.

- **GitHub Repository**: [https://github.com/morluto/rea](https://github.com/morluto/rea)
- **Primary Use Cases**:
  1. Reverse-engineering client-side web bundles and Electron apps to discover API contracts and schemas.
  2. Inspecting and decompiling Android APKs or native binaries (PE, ELF, Mach-O).
  3. Tracing execution paths, references, and state transitions to reimplement proprietary logic.
  4. Building clean-room software reconstructions backed by formal Evidence ledgers.

---

## 1. The 3-Phase Mental Model

REA enforces an evidence-based approach to prevent AI agents from hallucinating undocumented APIs or behaviors:

```
┌────────────────┐      ┌────────────────┐      ┌────────────────┐
│   DECOMPILE    │ ───> │   UNDERSTAND   │ ───> │    RECREATE    │
│ Recover Code & │      │ Trace Graphs & │      │ Implement with │
│ Static Bundles │      │ Dependencies   │      │ 100% Coverage  │
└────────────────┘      └────────────────┘      └────────────────┘
```

1. **Decompile & Recover**: Extract readable sources, disassembly, bytecode, manifests, or bundled scripts.
2. **Understand & Trace**: Build application graphs, track def-use chains, inspect call sites, map data flow.
3. **Recreate & Verify**: Write replacement code, generate test suites, and evaluate reconstruction coverage against an obligation ledger.

---

## 2. Core Capabilities by Target

REA is invoked via `npx rea-agents <command>` or `rea <command>`.

### A. Web Applications & JavaScript / Electron
Ideal for inspecting web apps, finding backend API schemas, and extracting frontend architectures.

```bash
# Capture and statically analyze a web application bundle
npx rea-agents analyze-web-bundle <URL>

# Export retained scripts into a directory for deep AST inspection
npx rea-agents export-web-scripts <URL> --output ./dump/scripts

# Recover readable JavaScript modules with source map provenance
npx rea-agents recover-javascript-sources <BUNDLE_PATH>

# Trace a specific UI action or feature to its handler and network calls
npx rea-agents trace-application-feature <SEED_FEATURE>

# Statically reconstruct a local JavaScript or Electron application
npx rea-agents analyze-javascript-application <APP_DIR>

# Observe live user-driven navigation session
npx rea-agents observe-web-session <URL> --duration 60s
```

### B. Android APKs & Mobile Targets
```bash
# Decode manifest, layouts, and strings with apktool
npx rea-agents decode-android-resources <APK_PATH>

# Search classes and method definitions
npx rea-agents search-android-classes <APK_PATH> --query "ApiClient"

# Trace incoming callers and references for a specific class or method
npx rea-agents trace-android-references <APK_PATH> --class "com.example.AuthService"

# Pull APKs directly from a connected device via ADB
npx rea-agents pull-adb-package <PACKAGE_NAME>
```

### C. Native Binaries & Managed (.NET)
```bash
# High-level binary overview (architecture, compiler, symbols, protections)
npx rea-agents analyze <BINARY_PATH>

# Decompile a function or routine into readable pseudocode
npx rea-agents decompile <BINARY_PATH> --target <FUNCTION_OR_ADDRESS>

# Search strings, exported procedures, or imported libraries
npx rea-agents search <BINARY_PATH> --query "API_KEY"

# List cross-references (xrefs) to a sensitive function or string
npx rea-agents xrefs <BINARY_PATH> --address <ADDR>

# Analyze .NET assemblies and member signatures
npx rea-agents project-managed-application-graph <DLL_PATH>
```

---

## 3. Strict Evidence Protocol (No-Hallucination Rule)

When an AI agent reports findings or uses REA to build replacement features, all conclusions **MUST** be classified into three distinct categories:

1. **SEEN (Direct Evidence)**:
   - Statements backed by explicit code lines, exported symbols, decoded strings, or captured HTTP frames.
   - *Example*: `"SEEN: Endpoint POST /api/v2/auth/token requires application/json with fields { client_id, signature } (found in bundle-core.js:1422)."`

2. **INFERRED (Logical Deduction)**:
   - Logical conclusions based on visible patterns or conventions, but not explicitly verified byte-for-byte.
   - *Example*: `"INFERRED: The signature parameter likely uses HMAC-SHA256 based on the 64-char hex length and presence of crypto-js in package dependencies."`

3. **UNKNOWN (Missing Information)**:
   - Gaps, encrypted payloads, obfuscated logic, or dynamic runtime behaviors that cannot be proven statically.
   - *Example*: `"UNKNOWN: The server-side validation algorithm for nonce rotation is server-private."`

---

## 4. Reconstruction Obligations & Verification

When rebuilding a reverse-engineered feature:

1. **Generate the Ledger**:
   ```bash
   npx rea-agents build-reconstruction-obligation-ledger <ANALYSIS_OUTPUT> --out obligations.json
   ```
2. **Evaluate Implementation Coverage**:
   ```bash
   npx rea-agents evaluate-reconstruction-coverage --ledger obligations.json --src ./src
   ```
   Ensures every extracted route, data structure, validation rule, and error code is implemented in the target project.

---

## 5. Clean-Room Building Synergy (REA + Dembrandt)

| Layer | Primary Tool | Artifact Produced | Rebuilt Target |
| :--- | :--- | :--- | :--- |
| **Logic & APIs** | **REA** | Application Graph, API Dossier, Obligation Ledger | Laravel Controller, Service, DTO, Client SDK |
| **Visuals & Tokens** | **Dembrandt** | `DESIGN.md`, Tailwind `@theme`, `shadcn/ui` theme | Blade Views, CSS variables, Component styling |

By combining **REA** for backend behavior and **Dembrandt** for frontend aesthetics, agents can recreate complex software with photographic fidelity and architectural precision.
