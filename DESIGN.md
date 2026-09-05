---
name: SSIS
description: Warm institutional gate for least-privilege student records work
colors:
  primary: "#176b52"
  primary-hover: "#10513d"
  primary-active: "#0b402e"
  primary-focus: "#237d60"
  brand-ink: "#174c3c"
  ink: "#21352e"
  muted: "#596960"
  paper: "#f4f6f5"
  surface: "#ffffff"
  line: "#d7e0db"
  nav-hover: "#f0f5f2"
  nav-current: "#e5f1eb"
  nav-current-ink: "#12533e"
  nav-link: "#45574d"
  field-border: "#9aada0"
  field-border-hover: "#617e6e"
  placeholder: "#647269"
  notice-bg: "#eaf5ef"
  notice-border: "#badac9"
  notice-ink: "#214e37"
  error-bg: "#fff0ee"
  error-border: "#ebc3be"
  error-ink: "#8b2721"
  danger: "#a12626"
  danger-hover: "#7e1b1b"
  secondary-border: "#a5b5ab"
  secondary-ink: "#344e3f"
  secondary-hover: "#edf3ef"
  table-head: "#edf2ef"
  table-head-ink: "#495e51"
  table-row-line: "#e3e9e5"
  table-row-hover: "#f7faf8"
  badge-bg: "#e6eee9"
  badge-ink: "#3e5748"
  selection-bg: "#cce7da"
  selection-ink: "#153d2d"
  disabled-bg: "#e3e9e5"
  dialog-scrim: "#13281fcc"
typography:
  brand:
    fontFamily: "Arial, sans-serif"
    fontSize: "17px"
    fontWeight: 700
    lineHeight: 1.5
    letterSpacing: "normal"
  headline:
    fontFamily: "Arial, sans-serif"
    fontSize: "28px"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Arial, sans-serif"
    fontSize: "19px"
    fontWeight: 700
    lineHeight: 1.35
    letterSpacing: "normal"
  body:
    fontFamily: "Arial, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  label:
    fontFamily: "Arial, sans-serif"
    fontSize: "14px"
    fontWeight: 700
    lineHeight: 1.5
    letterSpacing: "normal"
  nav:
    fontFamily: "Arial, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  muted:
    fontFamily: "Arial, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  table:
    fontFamily: "Arial, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  table-head:
    fontFamily: "Arial, sans-serif"
    fontSize: "13px"
    fontWeight: 700
    lineHeight: 1.5
    letterSpacing: "normal"
  badge:
    fontFamily: "Arial, sans-serif"
    fontSize: "12px"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  stat:
    fontFamily: "Arial, sans-serif"
    fontSize: "32px"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "normal"
rounded:
  control: "6px"
  notice: "8px"
  table: "10px"
  card: "12px"
  dialog: "12px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "20px"
  2xl: "24px"
  3xl: "28px"
  4xl: "32px"
  5xl: "36px"
  6xl: "48px"
  7xl: "64px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
    typography: "{typography.label}"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.surface}"
  button-primary-active:
    backgroundColor: "{colors.primary-active}"
    textColor: "{colors.surface}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.secondary-ink}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
    typography: "{typography.label}"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
  input-field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "10px 12px"
    height: "44px"
    typography: "{typography.body}"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "28px"
  badge:
    backgroundColor: "{colors.badge-bg}"
    textColor: "{colors.badge-ink}"
    rounded: "{rounded.control}"
    padding: "4px 9px"
    typography: "{typography.badge}"
  notice-success:
    backgroundColor: "{colors.notice-bg}"
    textColor: "{colors.notice-ink}"
    rounded: "{rounded.notice}"
    padding: "16px 20px"
  notice-error:
    backgroundColor: "{colors.error-bg}"
    textColor: "{colors.error-ink}"
    rounded: "{rounded.notice}"
    padding: "16px 20px"
  nav-link:
    backgroundColor: "transparent"
    textColor: "{colors.nav-link}"
    rounded: "{rounded.control}"
    padding: "9px 12px"
    height: "44px"
    typography: "{typography.nav}"
  nav-link-current:
    backgroundColor: "{colors.nav-current}"
    textColor: "{colors.nav-current-ink}"
    rounded: "{rounded.control}"
    padding: "9px 12px"
    height: "44px"
---

# Design System: SSIS

## Overview

**Creative North Star: "The Campus Gate"**

SSIS looks like a warm school-office gate, not a marketing site and not a dark security console. Staff pass through a clear institutional entry, then work on quiet paper-like surfaces where records, tables, and forms do the talking. The green accent means proceed and belong; mint washes mark status and selection without turning playful.

The system stays approachable and human while remaining formal. Density favors registrar speed: readable tables, bold labels, 44px controls, and dialogs that lift only when editing. Surfaces stay flat at rest so scan paths stay calm.

**Key Characteristics:**
- Soft institutional green on cool mint paper
- Flat tonal layering; dialog is the only lifted surface
- Bold 14px action/label type on Arial UI stack
- 6px controls, 12px cards/dialogs, mint current-nav and selection
- Role-aware chrome without decorative dashboard noise

## Colors

A single institutional teal-green accent on cool mint paper, with soft sage partitions and a reserved danger red for destructive actions.

### Primary
- **Institutional Teal-Green** (`{colors.primary}`): Primary buttons, links, caret, checkbox accent, skip-link border — the “proceed” voice of the Campus Gate.
- **Office Canopy** (`{colors.primary-hover}`): Hover deepen on primary actions.
- **Canopy Depth** (`{colors.primary-active}`): Pressed primary.
- **Focus Ring Green** (`{colors.primary-focus}`): 3px focus-visible outline for interactive controls.
- **Brand Ink** (`{colors.brand-ink}`): “Secure Student IS” wordmark in the header.

### Neutral
- **Quiet Clinic Paper** (`{colors.paper}`): Page background.
- **Surface White** (`{colors.surface}`): Cards, header bar, inputs, tables, dialogs.
- **Records Charcoal-Green** (`{colors.ink}`): Default body text.
- **Muted Sage** (`{colors.muted}`): Helper copy and empty-state tone.
- **Pale Partition** (`{colors.line}`): Header rule, card borders, table wraps, picker lists.
- **Nav Link Sage** (`{colors.nav-link}`): Default nav links.
- **Nav Hover Mist** (`{colors.nav-hover}`): Nav hover wash.
- **Nav Current Mint** (`{colors.nav-current}` / `{colors.nav-current-ink}`): Active route chip.
- **Field Border Sage** (`{colors.field-border}`): Input/select/textarea stroke; hover darkens to `{colors.field-border-hover}`.

### Status
- **Success Notice** (`{colors.notice-bg}` / `{colors.notice-border}` / `{colors.notice-ink}`): Flash and validation success.
- **Error Notice** (`{colors.error-bg}` / `{colors.error-border}` / `{colors.error-ink}`): Alerts and form errors.
- **Danger Action** (`{colors.danger}` / `{colors.danger-hover}`): Destructive buttons only.
- **Role Badge** (`{colors.badge-bg}` / `{colors.badge-ink}`): Compact role/status chips.
- **Selection Wash** (`{colors.selection-bg}` / `{colors.selection-ink}`): Text selection and checked student-picker rows (with `{colors.nav-current}`).

**The One Gate Accent Rule.** Institutional Teal-Green is for actions, links, focus, and brand — not for large fills, hero gradients, or decorative panels. Mint washes carry status; green carries intent.

**The Soft Partition Rule.** Prefer `{colors.line}` and tonal mint over heavy chrome borders. Partition records; don’t cage them.

## Typography

**Display Font:** Arial (with sans-serif fallback) — incumbent UI stack  
**Body Font:** Arial (with sans-serif fallback)

**Character:** Plain institutional sans — dense, legible, and unpretentious. Hierarchy comes from size and weight, not from display faces.

### Hierarchy
- **Brand** (700, 17px): Header product name.
- **Headline** (700, 28px / 25px ≤600px, line-height 1.2, tracking -0.025em): Page titles (`h1`).
- **Title** (700, 19px, line-height 1.35): Section and dialog titles (`h2`), student-picker legend.
- **Body** (400, 16px, line-height 1.5): Default copy and field text.
- **Label** (700, 14px): Form labels and primary/secondary button text.
- **Nav** (400/700, 14px): Navigation; current page is 700.
- **Muted** (400, 14px): Helper and empty-state supporting lines.
- **Table** (400, 14px) / **Table head** (700, 13px): Data density with tabular nums.
- **Badge** (400, 12px): Role chips.
- **Stat** (700, 32px): Dashboard count figures.

**The Weight-Over-Ornament Rule.** Emphasize with weight and size, never with decorative type, all-caps marketing labels, or a second display family unless product branding explicitly changes.

## Layout

Content lives in a centered `.wrap` lane (`max-width: 1184px`) with horizontal padding 32px (20px ≤600px). Header bar padding is 18px vertical; main content pads 36px top / 64px bottom (28px top on small screens).

Page toolbars use `.bar` — space-between, wrap, 20px gap — pairing a title with a primary action. Staff forms use a two-column `.grid` (24px column gap) that collapses to one column ≤600px. Dashboard `.stats` is a three-column card row that stacks on small screens. Narrow auth/setup cards cap at 480px and center with generous top margin.

Search rows flex with a capped input (`max-width: 440px`) that expands on mobile. Tables sit in rounded `.table-wrap` shells and may scroll horizontally on small viewports (table `min-width: 620px` ≤600px). Breakpoints observed: 900px (nav full-width), 600px (grid/stats/spacing densify). Print hides chrome and actions.

**The Work-Lane Rule.** Keep primary records work inside the 1184px lane. Do not introduce full-bleed marketing bands or side-panel heroes into Operate screens.

## Elevation & Depth

Depth is almost entirely tonal. Cards, tables, header, and inputs sit flat on Quiet Clinic Paper, separated by Pale Partition borders and soft mint washes. The only structural lift is the modal dialog: soft forest-tinted shadow (`0 24px 80px #12291f40`) over a dimmed scrim (`{colors.dialog-scrim}`). Hover states change background wash, not shadow.

### Shadow Vocabulary
- **Dialog lift** (`box-shadow: 0 24px 80px #12291f40`): Modal editors only.
- **No ambient card shadow:** Cards and table wraps use border + fill only.

**The Flat-By-Default Rule.** Surfaces are flat at rest. Shadows appear only when a dialog must leave the page plane. Do not add drop shadows to cards, stats, or nav.

## Shapes

Corners are gently institutional, never pill-like: controls and badges at 6px, notices at 8px, table wraps at 10px, cards and dialogs at 12px. Borders are 1px sage/partition strokes; focus uses a 3px solid Focus Ring Green with 3px offset. Checkboxes are square 18px with accent tint. Dialogs are borderless white sheets (radius 12px) relying on shadow + scrim instead of a stroke.

**The Soft Rect Rule.** Prefer 6–12px radii. Avoid fully rounded pills, floating circular FABs, and hard-offset “brutal” cards.

## Components

### Buttons
Refined and restrained — bold 14px labels, 44px min height, 6px radius, 10×18 padding.
- **Primary:** Institutional Teal-Green fill, white text; hover Office Canopy; active Canopy Depth.
- **Secondary:** White fill, sage border, secondary ink; hover soft mist.
- **Danger:** Reserved red fill for destructive confirms only.
- **Icon close:** 44×44 secondary control inside dialog bars.
- **Focus:** Shared 3px green ring on all interactive controls.
- **Disabled:** Disabled sage fill and muted ink.

### Cards / Containers
- **Corner Style:** Gently curved (12px)
- **Background:** Surface white with Pale Partition border
- **Shadow Strategy:** None at rest (see Elevation)
- **Internal Padding:** 28px (22px ≤600px)
- **Narrow auth card:** Same card language, max-width 480px

### Inputs / Fields
- **Style:** Full-width white fields, 6px radius, Field Border Sage, 10×12 padding, 44px min height
- **Hover:** Darker sage border
- **Focus:** Green ring (no thick blue browser glow)
- **Placeholder:** Muted sage gray
- **Error:** Notice-error block above the form (role=alert), not per-field color alone

### Navigation
Header on white with bottom Pale Partition. Brand wordmark at 17px/700 Brand Ink. Links are 14px sage chips (44px tall, 6px radius); hover mist; current page uses Nav Current Mint + bold ink. On ≤900px, nav wraps full width under the brand.

### Tables
White shell, 10px radius, partition border. Head row uses table-head tint and 13px bold labels. Body 14px with tabular nums; row hover mist; action links bold and 44px tall. Empty states center muted copy inside the table.

### Badges
Compact mint chips (12px text, 6px radius) for role labels beside the signed-in name.

### Notices
8px radius status banners: success mint or error blush, 16×20 padding. Used for flash messages and validation lists.

### Dialogs (signature)
Native `<dialog>` editors (480px default, 760px wide forms). White 12px sheet, 28px padding, title bar with close control, actions row separated by a top partition line. Student picker lists use bordered option rows with mint checked state. Body scroll locks while open.

### Skip link
Off-screen until focus; white pill with 2px primary border — accessibility chrome, not decoration.

## Do's and Don'ts

### Do:
- **Do** keep Institutional Teal-Green for actions, links, focus, and brand ink only.
- **Do** use flat cards/tables with Pale Partition borders and mint washes for status/selection.
- **Do** preserve 44px minimum hit targets on buttons, nav, and table actions.
- **Do** lift only dialogs (dialog shadow + scrim); leave other surfaces flat.
- **Do** collapse the form grid and stats to a single column at 600px.

### Don't:
- **Don't** introduce purple/indigo SaaS gradients, glow accents, or dark-mode shells.
- **Don't** add ambient shadows under cards, stats, or the header.
- **Don't** use pill-full radii, floating badge stickers, or icon-tile marketing headers.
- **Don't** invent school logos, testimonials, or support contacts in chrome — school name comes from settings.
- **Don't** replace role-aware nav with a generic mega-menu or dashboard widget wall.
