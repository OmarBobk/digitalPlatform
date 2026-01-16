---
name: debug-laravel
description: Systematic debugging for Laravel/Livewire issues: reproduce, isolate, verify fix, prevent regression.
---

# Debug Laravel

## Instructions
1. Ask for: error message, stack trace, relevant file, recent changes.
2. Identify layer: routing, middleware, Livewire, DB, queue, frontend.
3. Provide:
   - likely cause (ranked)
   - quickest verification step for each cause
4. Propose minimal fix first.
5. Add “guardrail” (test, logging, validation) if appropriate.

## Output format
- Symptoms
- Top causes (ranked)
- Verification steps
- Fix (minimal)
- Optional hardening
