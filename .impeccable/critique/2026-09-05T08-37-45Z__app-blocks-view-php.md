---
target: blocks multi-subject assignments
total_score: 30
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 1
target_identity: "file:C:\\xampp\\htdocs\\student-system\\app\\blocks-view.php"
target_fingerprint: "sha256:1c2778058ae255bcdb956aea8ac8d855cf19a80046b1bf3506ae3207bbdbe094"
target_path: "C:\\xampp\\htdocs\\student-system\\app\\blocks-view.php"
timestamp: 2026-09-05T08-37-45Z
slug: app-blocks-view-php
---
Method: dual-agent (A: 5933ddf2-9d71-44e5-9b30-39e112e68de6 · B: 80872577-c74b-4390-8d15-a03781919ac0)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 4 | Drift clear in-dialog; list still hides which blocks need Match |
| 2 | Match System / Real World | 3 | Match roster language; some sync wording may linger in flashes |
| 3 | User Control and Freedom | 3 | Cancel/close solid; no undo after Match/Remove |
| 4 | Consistency and Standards | 3 | Warn badge + Campus Gate patterns |
| 5 | Error Prevention | 3 | Confirms with counts; set-based drift after harden |
| 6 | Recognition Rather Than Recall | 3 | Chips help; full subject set inside dialog |
| 7 | Flexibility and Efficiency | 2 | No Match-all / bulk |
| 8 | Aesthetic and Minimalist Design | 3 | Action cell still dense when drifted |
| 9 | Error Recovery | 3 | Flash + validation + cancel edit |
| 10 | Help and Documentation | 3 | Strong inline helpers |
| **Total** | | **30/40** | **Good** |

## Design Specificity Verdict

**LLM:** Authored for SSIS registrar work (roster vs enrollment, Match roster, Membership → Subjects). Not generic CRUD.

**Detector:** `app/blocks-view.php` exit 0, `[]`.

**Overlays:** SKIPPED — no browser tools.

## Overall Impression

Polished Blocks surface clears the Good band. Snapshot semantics are visible via drift status and Match roster confirms.

## What's Working

1. Drift system (badge, banner, primary Match roster, row tint)
2. Numbered Membership / Subject steps
3. Subject chips on the list

## Priority Issues

1. **[P1]** No list-level drift / Match-all — open each block still
2. **[P2]** Action cell crowding when Match + Edit + Remove show together
3. **[P2]** Align any remaining “sync” flash copy to Match roster

## Persona Red Flags

Alex still wants bulk Match. Jordan needs one careful read of roster vs enrollments.

## Minor Observations

`+N more` not expandable; native confirm dialogs.

## Questions to Consider

Should Match-all live on the list? Should drift appear as a list badge without opening the dialog?
