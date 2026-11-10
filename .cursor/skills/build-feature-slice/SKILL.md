---
name: build-feature-slice
description: Build an end-to-end vertical slice (model → UI → validation → persistence → UX states) with minimal scope and safe diffs.
---

# Build Feature Slice

## When to use
- The agreed task is an end-to-end implementation slice. Follow the [collaboration agreement](../../../Docs/COLLABORATION_WORKFLOW.md) for Ask, Agent, review, and handoff.

## Instructions
1. Restate the user goal in 1 sentence.
2. Identify the minimum “happy path” flow (start → action → result).
3. List required files to touch (keep it small).
4. Implement in this order:
   - DB/model (if needed)
   - service/action (business logic)
   - Livewire component (thin)
   - Blade UI with the existing components and installed styling libraries
   - UX states (loading, error, empty)
5. Add basic validation and meaningful error handling.
6. Apply the relevant verification and handoff rules from the collaboration agreement.
