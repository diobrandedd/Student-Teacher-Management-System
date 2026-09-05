---
target: teacher grading surfaces
total_score: 24
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 5
target_identity: "file:C:\\xampp\\htdocs\\Student-Teacher-Management-System\\app\\teacher-grading-view.php"
target_fingerprint: "sha256:616d70d6b1b10ac040b42e10e7f5df03cec61ecc637ed32e7049e72f9c0ca54c"
target_path: "C:\\xampp\\htdocs\\Student-Teacher-Management-System\\app\\teacher-grading-view.php"
timestamp: 2026-09-05T14-34-00Z
slug: app-teacher-grading-view-php
---
Method: dual-agent (A: 9b629c3d-223e-4419-b4e1-9ef6a3fad638 · B: 38011882-222b-4603-8351-a5b085f7ea44)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Phase/graded cues exist, but no roster readiness (X/Y graded) or live weight totals; submit readiness only appears after failure |
| 2 | Match System / Real World | 3 | Midterm/Finals and category names fit PH office speech; Phase/blend still system-y |
| 3 | User Control and Freedom | 3 | Back/Cancel/confirm solid; no next-student path after score save |
| 4 | Consistency and Standards | 3 | Shared cards/tables; Graded plain text vs badges; casing diverge |
| 5 | Error Prevention | 2 | Submit available when incomplete; weight sums not constrained in UI |
| 6 | Recognition Rather Than Recall | 2 | Setup vs scoring on two walls; Enter scores hides formula context |
| 7 | Flexibility and Efficiency | 1 | One-student Enter scores only |
| 8 | Aesthetic and Minimalist Design | 3 | Calm Operate layout; Submit card competes with roster |
| 9 | Error Recovery | 3 | Plain server messages; no unlock after lock |
| 10 | Help and Documentation | 2 | Muted helpers; no first-run path scaffolding |
| **Total** | | **24/40** | **Acceptable** |

## Design-specificity verdict

Surfaces inherit SSIS Campus Gate chrome and registrar language (draft until submit, midterm→finals lock, category weights, teacher wall). Composition is still interchangeable list→detail CRUD with weak readiness ritual around irreversible submit.

## Detector evidence

CLI detect on teacher-grading-view.php and grading-view.php: exit 0, 0 findings. Browser visualization skipped (no browser automation tool).

## Priority issues

- [P1] One-student Enter scores only — add next/prev and save-and-next
- [P1] Submit without readiness UI — show X/Y graded; disable submit until ready
- [P1] Split setup vs scoring walls — cross-links and workflow copy
- [P1] Status not scannable — badges for graded/phase variants
- [P1] Admin weights lack sum-to-100 feedback — live totals

## Persona red flags

Alex: no bulk/next. Jordan: unclear My subjects → Assigned Blocks order. Casey: Submit above long tables.

## Minor observations

Title casing; category grouping on Enter scores; weight list competing with table.
