---
description: Stage reviewed changes and create a Git commit using the shell git CLI.
argument-hint: "[commit message]"
allowed-tools: Bash(git status:*), Bash(git diff:*), Bash(git add:*), Bash(git commit:*)
---

You are preparing a Git commit for this repository.

Rules:
- Never call a tool or command named `git.commit`; it does not exist in this environment.
- Never treat an argument like `/11` as a command name. If it is provided, treat it only as user text or ask for the intended commit message.
- Use only shell commands with the real Git CLI: `git status`, `git diff`, `git add`, and `git commit`.
- Do not run `migrate:refresh`, database reset commands, or destructive Git commands.
- Preserve unrelated user changes. Stage only files that belong to the current requested fix.

Flow:
1. Run `git status --short`.
2. Inspect relevant diffs with `git diff -- <path>` before staging.
3. Stage only the intended files with `git add -- <path>`.
4. Commit with `git commit -m "$ARGUMENTS"` when a message is provided.
5. If no message is provided, write a concise message from the staged diff and run `git commit -m "<message>"`.
6. Report the commit hash from the successful command output.
