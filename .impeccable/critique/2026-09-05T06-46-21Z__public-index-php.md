---
target: public/index.php
total_score: 22
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:C:\\xampp\\htdocs\\student-system\\public\\index.php"
target_fingerprint: "sha256:c050f1fc7d5e9cbd648dae701a4264b219fc3d6d85ad5f93bb516ee016afbc51"
target_path: "C:\\xampp\\htdocs\\student-system\\public\\index.php"
timestamp: 2026-09-05T06-46-21Z
slug: public-index-php
closed: true
---
Method: dual-agent (A: 37e170c6-9844-44f5-adbc-0ee17fbe838f · B: 06c99fca-532c-45ef-bd37-f2ba7a179d3e)

# SSIS critique (Operate)
Campus Gate tokens and dialog-first editing are strong; Operate UX still reads as generic CRUD. Design health **22/40** (Acceptable).

## Heuristics
| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Settings notices never surface in chrome; modal fetch lacks explicit loading copy |
| 2 | Match System / Real World | 3 | Dashboard badge dumps raw role tokens |
| 3 | User Control and Freedom | 3 | Students search lacks always-visible Clear |
| 4 | Consistency and Standards | 2 | Mixed Add patterns; Users table missing thead |
| 5 | Error Prevention | 2 | Deactivate is silent checkbox; year level free text |
| 6 | Recognition Rather Than Recall | 2 | Prerequisite chains are footnotes |
| 7 | Flexibility and Efficiency | 1 | Students LIMIT 100 without pagination |
| 8 | Aesthetic and Minimalist Design | 3 | Admin nav ~11 equal-weight links |
| 9 | Error Recovery | 3 | Login clears username; recovery path thin |
| 10 | Help and Documentation | 1 | No in-product help for Blocks/lockout/settings |
| **Total** | | **22/40** | **Acceptable** |

## Design specificity
Tokens and access contract are product-true; composition is still interchangeable school-SIS CRUD. school_name unused in chrome.

## Detector
CLI detect on PHP views: `[]` (clean). Browser overlay unavailable (no automation).

## Priority issues
1. **[P1] Nav wall** — Split primary / catalog / administration groups.
2. **[P1] Student form overload + name/year validation** — Group fields; year select 1–8; allow real names.
3. **[P1] Students list efficiency** — Paginate like Teachers/Blocks; clear search; result count.
4. **[P2] Dead settings** — Surface school_name + maintenance_notice in header.
5. **[P2] Login recovery** — Preserve username; account-neutral retry/lockout guidance.
