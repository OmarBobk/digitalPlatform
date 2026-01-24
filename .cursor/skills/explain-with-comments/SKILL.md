---
name: explain-with-comments
description: Add minimal, high-signal comments so Omar can understand the code later. Comments must explain intent and gotchas, not syntax.
---
## Purpose
Add minimal, high-signal comments so Omar can understand the code later.
Comments must explain intent and gotchas, not syntax.

## When to use
- After generating or refactoring Livewire/Alpine/Flux code
- Before submitting a PR
- When adding timers, listeners, callbacks, or async flows

## Rules
- Explain WHY, not WHAT.
- Do not comment obvious syntax.
- Prefer section comments over line-by-line comments.
- Add comments for:
    - lifecycle timing (mount/init/destroy, listeners, interval cleanup)
    - data flow (where state comes from, where it goes)
    - edge cases and failure modes
    - any “non-obvious” decision

## Output format
- Add comments directly in the code.
- Keep each comment 1–2 lines.
- Do not change behavior unless explicitly asked.

## Checklist
- No noisy comments
- Comments exist at risky points
- Alpine + Livewire boundaries explained
