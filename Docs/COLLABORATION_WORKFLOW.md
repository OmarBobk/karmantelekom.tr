# Karman Telekom Collaboration Agreement

This is the source of truth for how Omar, ChatGPT, and Cursor plan, implement, verify, and hand off work. [PROJECT_SPEC.md](PROJECT_SPEC.md) records approved business rules. [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) records sequencing, acceptance criteria, and progress. The root [AGENTS.md](../AGENTS.md) and [scoped Cursor rules](../.cursor/rules) supply technical conventions and implementation detail. Apply them within this workflow.

## 1. Responsibilities and decisions

- **ChatGPT** clarifies requirements, plans work, writes Cursor tasks, reviews the actual code and results, and tracks progress against the plan.
- **Cursor** inspects the current code, proposes an implementation, implements the agreed scope, and verifies the result.
- **Omar** owns business rules, permissions, and financial operations. Batch missing decisions that materially affect the task. Give a recommendation and the practical consequence of each viable option. Wait for approval before implementing the affected behavior. Continue independent work within approved rules.
- Distinguish approved decisions, proposals, and inferences from code in tasks, reviews, and project notes. Existing behavior describes the current system. Business approval comes from Omar.
- The implementer decides routine technical and reversible UX details within the agreed scope.

## 2. Work cycle

Use **Ask → Agent → Review → Done** for new phases and features.

1. **Ask.** Work read-only. Inspect the actual entry points, dependencies, and relevant tests. Propose one cohesive medium-sized batch, acceptance criteria, missing decisions, and a proportionate verification plan.
2. **Agent.** After reviewing the Ask result and settling material decisions, ChatGPT writes the Agent prompt from those findings. Cursor implements and verifies the agreed batch.
3. **Review.** Compare the pushed code and evidence with the acceptance criteria, then resolve the relevant findings.
4. **Done.** Record acceptance and the current review, merge, and deployment status as described below.

A specific, evidenced defect in an existing PR can go straight to Agent. Return to planning if scope changes or a new business rule appears. When Omar asks to accelerate, group related work and remove repeated checks while keeping the required inspection and decisions.

## 3. Batch size and autonomy

- Each batch delivers a usable, testable path from entry to outcome in a reviewable PR. Keep unrelated phases and fixes separate. Changes to schema or existing functionality must be part of the agreed scope.
- Within one task, Agent does the targeted inspection, implementation, self-review, related fixes, verification, relevant documentation, and commit.
- Correct directly affected older tests whose fixtures or expectations are wrong for the agreed behavior. Explain the correction. Briefly record unrelated defects for later.
### Keep the implementation simple

- In Ask, start with the most direct change that meets the agreed acceptance criteria. Identify the existing code or framework capability it can reuse. Compare another design only when a concrete constraint makes the direct approach insufficient.
- In Agent, keep one authoritative implementation of each business rule and reuse it at the affected entry points. Add an abstraction only to enforce a needed boundary or share logic across real callers. Keep simple logic near the code that uses it.
- Tie each new layer, dependency, state, configuration option, or piece of infrastructure to a current requirement or demonstrated risk. Explain briefly what the simpler approach cannot do. Defer speculative capabilities and unrelated refactors.
- Simplify the implementation while preserving required authorization, data integrity, concurrency, recovery, and compatibility. Keep the protections that address a concrete failure mode.
- In Review, ask which acceptance criterion or failure mode each significant addition serves. Remove unused indirection, duplicate rule paths, and optional scope growth from the proposed change. Judge simplicity by how easily the resulting behavior can be understood, tested, and changed.

## 4. Proportionate verification and cost

- Start with the previous inspection report when available. Use targeted search and bounded reads. Reinspect changed areas and any dependency a finding makes relevant.
- Match verification to impact. Instructions, copy, and simple styling need appropriate diff, link, structure, or markup checks. Use suitable existing tests first. Add tests for important behavior or a specific uncovered regression.
- Run the focused checks after the final relevant edit. Reuse successful results while the relevant code and dependencies remain unchanged. Rerun for a relevant edit, a failure, or evidence of flakiness.
- Run the full suite for broad impact or a demonstrated need, including CI requirements. Use real concurrency tests for lock changes, reservations, or competition over money or stock.
- Run the project formatter on the affected files. Build assets when bundling needs verification.
- Omar owns routine manual walkthroughs. Give one integrated scenario or at most three short checks, with route or link, role, test data or input, and expected result. Label them pending until confirmed.
- Use focused browser automation when requested or to reproduce a defect that depends on browser behavior or network timing. Distinguish browser evidence from PHP or component tests.
- Keep Cursor reports short. Include the important files, verification actually performed and its result, the commit, and material blockers or remaining checks.

## 5. Tools and framework choices

- Before each task, ChatGPT states the mode, relevant repository references, and any MCP or skill actually needed. Start with the available tools and propose additions for a specific gap.
- Check relevant versions from dependency manifests and lockfiles or installed package metadata. Discover components in the current code. Use installed Laravel, Livewire, Alpine, Tailwind, and other libraries within their verified capabilities.
- Use Laravel Boost or version-matched official documentation for uncertain APIs or version-specific behavior. Apply a relevant scoped rule or skill only when it serves the task.
- Use Alpine or native browser behavior for local interaction and temporary UI state. Use Livewire for authorized reads and actions. The server decides authorization, money, and inventory.
- Keep opening menus and preloaded details local when practical. Tune search, pagination, queries, and payloads to the actual workflow. Check N+1 behavior when the change can introduce it.

## 6. UI quality in the affected flow

Choose the checks that apply and fix in-scope problems during implementation.

- Choose semantic field types, suitable mobile keyboards, paste handling, autocomplete, labels, and validation. Treat phone numbers and identifiers as strings and preserve leading zeroes.
- Use the project's approved money format. Convert exactly at the server boundary and reject ambiguous input. When changing a money input, check caret movement, Backspace, and paste.
- Check Arabic RTL, English LTR, and the direction of identifiers and amounts embedded in Arabic text.
- Check keyboard navigation, focus on dialog entry and close, touch targets, and loading, empty, error, and success states.
- Preserve input after errors. Keep delayed search results from overriding scanner feedback. Verify that Enter submits the current value.
- Use design judgment within the existing interface and visual identity. Consult `DESIGN.md` when the current project instructions call for it and it is available. Older guidance does not make it mandatory.

## 7. Behavior and data safety

- Validate authorization, branch scope, identifiers, and values on the server, including direct actions and modified IDs.
- Where relevant, check stale tabs, revalidation before payment, reloads, lost responses, replay, concurrent requests, and failure after a process starts.
- Check lock order, including locks implicitly acquired by foreign keys. Keep related writes atomic and preserve recovery and idempotency.
- Check old records and callers, especially retry fingerprints and schema upgrades. Preserve financial and inventory history through documented correction flows using approved rules.
- Treat the application database as containing data worth preserving. Verify what is installed before changing a migration. Use forward upgrades where needed. Run tests and automated experiments on isolated databases. Provide precise instructions and an appropriate review before applying changes to the application database.

## 8. Review and handoff

- ChatGPT reviews the actual pushed commit and names the SHA and scope. If the code is inaccessible, state that and limit conclusions to the report.
- Group evidenced findings on the affected flow into one review round where practical. Separate merge blockers from optional improvements.
- Distinguish checks personally verified, results reported by Cursor, and pending manual checks.
- Cursor commits. Omar pushes, opens the PR, and squash-merges by default. A specific delegation can change who performs that action. Give Omar the commands and the correct point in the workflow to run them.
- Done requires satisfied acceptance criteria and a clear record of review, merge, and rollout status. Code completion alone does not establish deployment or migration on the application database.

## 9. Resuming work

- Track completed, active, and deferred work and open decisions in the existing project files. On resumption, read this agreement, the relevant spec and plan sections, and the current branch or PR evidence before planning or editing.
- At the start of a task, state its place in the plan and work cycle, then give only the requested next step.
- Carry forward Omar's latest standing instruction. Record changes to earlier decisions explicitly in the appropriate source. Keep one-off requests scoped to their task.
- On a new conversation, first point the assistant to this file and the relevant current plan section and PR or commit.
