---
target: polished student system
total_score: 25
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\xampp\\htdocs\\student-system\\public\\index.php"
target_fingerprint: "sha256:e2238751941abf5d54e3d18ba473dea01d7b463ee912c5588b249e3d2c92031c"
target_path: "C:\\xampp\\htdocs\\student-system\\public\\index.php"
timestamp: 2026-09-05T02-56-16Z
slug: public-index-php
---
Method: dual-agent (A: /root/critique_a · B: /root/critique_b)

# Student system critique
The polished system is visually coherent but still feels like a basic internal tool. The next step toward professional quality is completing everyday workflows, not adding decoration.

## Scope and design health
Live desktop (1280×720) and mobile (390×844) login review; authenticated pages reviewed from source only. No credentials entered or records changed. Provisional design health: 25/40.
Scores /4: system status 3; real-world language 3; user control 3; consistency 3; error prevention 2; recognition 3; efficiency 2; aesthetics 3; error recovery 2; help 1.
These are qualitative review scores, not an accessibility certification or measured usability test.

## Specificity and strengths
Restrained green/white styling is coherent but interchangeable with other internal tools. Institutional identity and access guidance would add useful specificity.
The login has a clear hierarchy, two labeled fields, and one primary action. Desktop/mobile rendering fits without visible clipping. Labels, keyboard-focus styling, role-aware navigation, success messages and empty states are sound foundations.

## Priority issues
1. P1 — Student names are rejected. public/index.php:174–175 uses ASCII-only patterns; public/app.js:45–46 and app/bootstrap.php:92–93 reject spaces and punctuation. Names such as Mary Ann and De la Cruz fail. Align HTML, JavaScript and PHP validation with real naming requirements. Suggested: $impeccable harden.
2. P1 — Sign-in failure lacks a recovery path. public/index.php:30,171 gives a generic error and clears the username. Preserve username, retain account-neutral errors, add safe retry guidance and a genuine support route. Suggested: $impeccable harden.
3. P2 — Record browsing does not scale. public/index.php:93 silently limits results to 100. Add pagination and a result count, then useful course/year/status filters; apply filter semantics consistently to exports. Suggested: $impeccable shape.
4. P2 — Student forms need meaningful groups. public/index.php:174–175 presents 11–12 controls in a flat grid. Group identity, academics, and contact/access; label optional fields and show accepted year/GPA ranges before submission. Suggested: $impeccable clarify.
5. P2 — Institutional context is missing. public/index.php:159,164,171,180 saves school name and maintenance notice but does not display them in the shared interface. Use the configured identity and explain where issued accounts come from. Do not invent support details. Suggested: $impeccable clarify.

## Cognitive load and personas
Login has low cognitive load. Source shows seven administrator navigation links and a long student form; grouping deserves review, but option count alone is not proof of overload.
First-time students can find Sign in but cannot learn whom to contact. Staff handling real student names encounter validation barriers. Frequent record users cannot browse beyond the first 100 matches.
The emotional journey begins calmly but can end in uncertainty after sign-in failure.

## Minor observations
Unify blocking window.alert validation with inline errors; connect field-specific errors programmatically. Add username autocapitalization/spellcheck settings appropriate to identifiers. Keep the useful whitespace.

## Detector
One warning: overused-font, public/style.css:2 (Arial). This is a weak aesthetic signal, not a functional defect; retaining the established font is reasonable. No browser warning/error logs on initial login. No mutable injection API, so no overlay; screenshots and CLI are the fallback.

## Next decisions
Which comes first: forms and recovery, record browsing, or institutional identity?
Scope: first three issues or all five?
