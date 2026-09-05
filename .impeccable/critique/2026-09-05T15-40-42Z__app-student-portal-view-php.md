---
scores: [object Object]
band: Excellent
assessment_provenance: parallel-subagents; browser skipped (no automation)
total: 36
max: 40
target: app/student-portal-view.php
target_identity: "file:C:\\xampp\\htdocs\\Student-Teacher-Management-System\\app\\student-portal-view.php"
target_fingerprint: "sha256:0970c49825079675413330ee01a6186763efa499843a1cb17349b280c1a8b191"
target_path: "C:\\xampp\\htdocs\\Student-Teacher-Management-System\\app\\student-portal-view.php"
timestamp: 2026-09-05T15-40-42Z
slug: app-student-portal-view-php
---
# Critique — Student portal (My Subjects)

**Target:** `app/student-portal-view.php` (+ student profile / nav in `public/index.php`)
**Mode:** Operate
**Provenance:** Assessment A + B via parallel subagents; detector clean; browser skipped (no automation). Polish applied after first 25/40 pass.

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|------:|-----------|
| 1 | Visibility of System Status | 4 | Status badges, list grades, session-who, lookup alert |
| 2 | Match System / Real World | 4 | Student publish labels; Midterm/Finals/Overall |
| 3 | User Control and Freedom | 4 | Dialog Close/×/Esc; profile Back to My Subjects |
| 4 | Consistency and Standards | 3 | List "—" vs dialog prose for unpublished |
| 5 | Error Prevention | 4 | Own-enrollment gate; readonly legal names; CSRF |
| 6 | Recognition Rather Than Recall | 4 | Grade columns on list; dialog meta |
| 7 | Flexibility and Efficiency | 2 | Keyboard rows only; no filter/sort |
| 8 | Aesthetic and Minimalist Design | 4 | Thin student wall; one-job sections |
| 9 | Error Recovery | 3 | Subject-not-found alert; profile errors |
| 10 | Help and Documentation | 4 | Privacy + Overall blend weights shown |
| **Total** | | **36/40** | **Excellent** |

## Design Specificity Verdict

Grounded in SSIS Operate language (teal-green, Clickable-Row, published-grades-only, student wall). Detector: 0 findings. Browser overlays: skipped (no automation).

## Overall Impression

Student home now answers "what are my grades?" on the list; the dialog adds teacher context. Display name appears next to Sign out.

## What's Working

1. Clickable-row list → dialog on current page
2. Published-only grades with clear Status labels
3. Session identity chip honors display_name

## Priority Issues

1. [P2] Flexibility: no search/filter on large subject lists
2. [P2] Unpublished cell wording still differs slightly list vs dialog

## Questions skipped: polish loop completed to Design Health >= 35; no further question this turn.
