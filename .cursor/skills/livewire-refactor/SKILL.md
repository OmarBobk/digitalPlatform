---
name: livewire-refactor
description: Refactor a large Livewire component into thin UI + services/actions while preserving behavior.
---

# Livewire Refactor

## When to use
- Component is too big, mixes concerns, hard to test/maintain.

## Instructions
1. Identify responsibilities: UI state, validation, persistence, side effects.
2. Extract business logic into an Action/Service:
   - one public method per use-case (e.g., UpdateProduct::run()).
3. Keep Livewire methods as orchestration only.
4. Reduce duplicated state:
   - prefer computed properties / derived values.
5. Keep public API stable (events, emitted actions, view bindings).
6. Provide a diff-friendly sequence of commits/steps.

## Acceptance checks
- Same behavior
- Same events
- Same validation messages (unless asked to change)
- Smaller component, clearer naming
