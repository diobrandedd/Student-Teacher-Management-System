---
target: public/index.php
total_score: 28
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\xampp\\htdocs\\student-system\\public\\index.php"
target_fingerprint: "sha256:b125413ff618ec90f3f9cb70def971504b5d165150154a96addd9a4decda1971"
target_path: "C:\\xampp\\htdocs\\student-system\\public\\index.php"
timestamp: 2026-09-05T06-53-36Z
slug: public-index-php
---
Method: dual-agent (A: be922f4e-d44f-4d86-aa2a-09431381cbaf · B: d26ce102-6196-4c78-ac16-1874305678a8)

# SSIS critique (Operate, post-polish)

Campus Gate now reads as product-specific institutional Operate UI. Design health **28/40** (Good).

## Heuristics
| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Modal "Loading form…" stays sr-only for sighted users |
| 2 | Match System / Real World | 3 | Audit log still shows raw CREATE/UPDATE tokens |
| 3 | User Control and Freedom | 3 | Cancel/clear work; no undo after save |
| 4 | Consistency and Standards | 3 | Add patterns still split (dialog button vs ?add=1 vs Users) |
| 5 | Error Prevention | 3 | Strong selects/validation; deactivate still no confirm dialog |
| 6 | Recognition Rather Than Recall | 3 | Block picker shows course/year; long lists lack filters |
| 7 | Flexibility and Efficiency | 2 | Pagination/search yes; no bulk/sort/shortcuts |
| 8 | Aesthetic and Minimalist Design | 3 | Campus Gate clean; admin chrome still dense |
| 9 | Error Recovery | 3 | Login recovery polished; CSRF/403 still bare exits |
| 10 | Help and Documentation | 2 | Inline helpers good; no persistent task help |
| **Total** | | **28/40** | **Good** |

## Design specificity
Authored for SSIS / Campus Gate — school-office gate with settings-driven identity, not interchangeable SaaS. Remaining genericism is list/search/dialog structure, not the visual world.

## Detector
CLI detect: `[]` (clean). Browser overlay unavailable (no automation).

## Priority issues
1. **[P1] Sighted modal loading missing** — `#modal-status` stays `.sr-only`; make busy state visible.
2. **[P1] Raw 403/CSRF exits** — bare `exit()` abandons Campus Gate shell; render card + recovery.
3. **[P2] Admin nav still dense** — groups help; collapse secondary or promote Dashboard jobs.
4. **[P2] Student form is one slab** — fieldsets organize but do not stage Contact/Assignment.
5. **[P3] Add-flow pattern drift** — standardize opener pattern across modules.
