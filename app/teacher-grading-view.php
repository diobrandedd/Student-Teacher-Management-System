<?php
declare(strict_types=1);
global $page, $assignments, $assignment, $assignmentId, $items, $itemErrors, $itemInput, $editItemId;
global $weights, $lockedTerms, $phase, $roster, $submitErrors, $submission;
global $student, $studentId, $scoreErrors, $scoreMap, $currentTerm, $locked;
global $gradedCount, $rosterCount, $canSubmit, $submitBlockReason;
global $itemsByCategory, $prevStudentId, $nextStudentId, $rosterPosition, $rosterTotal;

function grading_phase_badge_class(string $phase): string
{
    return match ($phase) {
        'complete' => 'badge badge-phase-complete',
        'final' => 'badge badge-phase-final',
        default => 'badge badge-phase-midterm',
    };
}
?>
<?php if ($page === 'my_subjects'): ?>
<div class="bar"><h1>My Subjects</h1></div>
<p class="muted">Step 1: click a subject row to add titled score items for midterm and finals. Step 2: enter and submit scores on <a href="?page=assigned_blocks">Assigned Blocks</a>. Weights below are set by the administrator.</p>
<section class="card">
  <h2>Grading percentages</h2>
  <p class="muted">Read-only. Ask an administrator to change these under Grading system.</p>
  <h3 class="weight-group-title">Category percentages</h3>
  <p class="muted">How each midterm or finals grade is built from score items.</p>
  <ul class="weight-list">
    <?php foreach (grading_category_keys() as $key): ?>
    <li><strong><?=e(grading_category_labels()[$key])?></strong> <?=e(format_grade((float)$weights[$key]))?>%</li>
    <?php endforeach; ?>
  </ul>
  <h3 class="weight-group-title">Overall grade</h3>
  <p class="muted">How midterm and finals combine into the overall grade.</p>
  <ul class="weight-list">
    <li><strong>Midterm</strong> <?=e(format_grade((float)$weights['midterm']))?>%</li>
    <li><strong>Finals</strong> <?=e(format_grade((float)$weights['final']))?>%</li>
  </ul>
</section>
<p class="muted table-scroll-hint">Click a row to manage score items.</p>
<div class="table-wrap">
  <table>
    <thead><tr><th>Block</th><th>Subject</th><th>Phase</th><th>Roster</th></tr></thead>
    <tbody>
    <?php if (!$assignments): ?>
      <tr><td colspan="4" class="empty">No subjects assigned yet. An administrator assigns you to block subjects under Blocks.</td></tr>
    <?php else: foreach ($assignments as $row): ?>
      <tr class="row-link" data-href="?page=my_subjects&amp;assignment_id=<?=(int)$row['id']?>" tabindex="0" aria-label="Manage score items for <?=e($row['subject_code'].' in '.$row['block_name'])?>">
        <td><?=e($row['block_name'])?></td>
        <td><strong><?=e($row['subject_code'])?></strong> · <?=e($row['subject_name'])?></td>
        <td><span class="<?=e(grading_phase_badge_class($row['phase']))?>"><?=e($row['phase_label'])?></span></td>
        <td><?=(int)$row['roster_count']?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php if ($assignment): ?>
<dialog open class="wide-dialog" aria-labelledby="subject-items-title" data-return-url="?page=my_subjects">
  <div class="bar">
    <div>
      <h2 id="subject-items-title"><?=e($assignment['subject_code'])?> · <?=e($assignment['subject_name'])?></h2>
      <p class="muted">Block <?=e($assignment['block_name'])?> · <span class="<?=e(grading_phase_badge_class($phase))?>"><?=e(assignment_phase_label($phase))?></span></p>
    </div>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button>
  </div>
  <?php errors($itemErrors); ?>
  <section class="form-section">
    <h3><?=$editItemId ? 'Edit score item' : 'Add score item'?></h3>
    <?php if ($phase === 'complete'): ?>
    <p class="muted">Both terms are submitted. Score items are locked.</p>
    <?php else: ?>
    <form method="post" action="?page=my_subjects&amp;assignment_id=<?=(int)$assignmentId?>">
      <?=csrf_field()?>
      <input type="hidden" name="form" value="item">
      <input type="hidden" name="item_action" value="save">
      <?php if ($editItemId): ?><input type="hidden" name="item_id" value="<?=(int)$editItemId?>"><?php endif; ?>
      <div class="grid">
        <div>
          <label for="item-term">Term</label>
          <select id="item-term" name="term" required>
            <option value="midterm" <?=$itemInput['term']==='midterm'?'selected':''?> <?=!empty($lockedTerms['midterm'])?'disabled':''?>>Midterm</option>
            <option value="final" <?=$itemInput['term']==='final'?'selected':''?> <?=!empty($lockedTerms['final'])?'disabled':''?>>Finals</option>
          </select>
        </div>
        <div>
          <label for="item-category">Category</label>
          <select id="item-category" name="category" required>
            <?php foreach (grading_category_keys() as $key): ?>
            <option value="<?=e($key)?>" <?=$itemInput['category']===$key?'selected':''?>><?=e(grading_category_labels()[$key])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="item-title">Title</label>
          <input id="item-title" name="title" required maxlength="150" value="<?=e($itemInput['title'])?>" placeholder="e.g. Quiz 1">
        </div>
        <div>
          <label for="item-max">Max score</label>
          <input id="item-max" name="max_score" type="number" min="0.01" step="0.01" required value="<?=e($itemInput['max_score'])?>">
        </div>
      </div>
      <div class="actions">
        <button type="submit"><?=$editItemId ? 'Update item' : 'Add item'?></button>
        <?php if ($editItemId): ?><a class="secondary" href="?page=my_subjects&amp;assignment_id=<?=(int)$assignmentId?>">Cancel</a><?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
  </section>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Term</th><th>Category</th><th>Title</th><th>Max</th><th><span class="sr-only">Edit or remove</span></th></tr></thead>
      <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="5" class="empty">No score items yet. Add quizzes, activities, attendance, projects, and an exam for each term you will grade.</td></tr>
      <?php else: foreach ($items as $item):
        $termLocked = !empty($lockedTerms[$item['term']]);
      ?>
        <tr>
          <td><?=e($item['term']==='final'?'Finals':'Midterm')?></td>
          <td><?=e(grading_category_labels()[$item['category']] ?? $item['category'])?></td>
          <td><?=e($item['title'])?></td>
          <td><?=e(format_grade((float)$item['max_score']))?></td>
          <td>
            <?php if (!$termLocked): ?>
            <form method="post" class="inline-actions" action="?page=my_subjects&amp;assignment_id=<?=(int)$assignmentId?>">
              <?=csrf_field()?>
              <input type="hidden" name="form" value="item">
              <input type="hidden" name="item_id" value="<?=(int)$item['id']?>">
              <button type="submit" name="item_action" value="edit" class="linkish">Edit</button>
              <button type="submit" name="item_action" value="delete" class="linkish danger-text" data-confirm="Remove this score item and any draft scores for it?">Remove</button>
            </form>
            <?php else: ?>
            <span class="muted">Locked</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</dialog>
<?php endif; ?>

<?php elseif ($page === 'assigned_blocks'): ?>
<div class="bar"><h1>Assigned Blocks</h1></div>
<p class="muted">Click a block subject row to enter draft scores. When every student is graded for the term, submit midterm, then later finals. Set score items first on <a href="?page=my_subjects">My Subjects</a>.</p>
<p class="muted table-scroll-hint">Click a row to open the roster.</p>
<div class="table-wrap">
  <table>
    <thead><tr><th>Block</th><th>Subject</th><th>Phase</th><th>Roster</th></tr></thead>
    <tbody>
    <?php if (!$assignments): ?>
      <tr><td colspan="4" class="empty">You have no assigned block subjects yet.</td></tr>
    <?php else: foreach ($assignments as $row): ?>
      <tr class="row-link" data-href="?page=assigned_blocks&amp;assignment_id=<?=(int)$row['id']?>" tabindex="0" aria-label="Open roster for <?=e($row['subject_code'].' in '.$row['block_name'])?>">
        <td><?=e($row['block_name'])?></td>
        <td><strong><?=e($row['subject_code'])?></strong> · <?=e($row['subject_name'])?></td>
        <td><span class="<?=e(grading_phase_badge_class($row['phase']))?>"><?=e($row['phase_label'])?></span></td>
        <td><?=(int)$row['roster_count']?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php if ($assignment): ?>
<dialog open class="wide-dialog" aria-labelledby="roster-title" data-return-url="?page=assigned_blocks">
  <div class="bar">
    <div>
      <h2 id="roster-title"><?=e($assignment['block_name'])?></h2>
      <p class="muted"><?=e($assignment['subject_code'])?> · <?=e($assignment['subject_name'])?> · <span class="<?=e(grading_phase_badge_class($phase))?>"><?=e(assignment_phase_label($phase))?></span></p>
    </div>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close roster">×</button>
  </div>
  <?php errors($submitErrors); ?>
  <?php if ($phase !== 'complete'): ?>
  <section class="form-section grading-ready-card">
    <h3>Submit <?= $phase === 'midterm' ? 'midterm' : 'finals' ?></h3>
    <p class="ready-meter" role="status">
      <strong><?=(int)$gradedCount?></strong> of <strong><?=(int)$rosterCount?></strong> students graded for this term
      <?php if ($rosterCount > 0): ?>
      <span class="ready-bar" aria-hidden="true"><span class="ready-bar-fill" style="width:<?= $rosterCount ? min(100, (int)round(100 * $gradedCount / $rosterCount)) : 0 ?>%"></span></span>
      <?php endif; ?>
    </p>
    <p class="muted">Draft scores stay private until you submit. Submitting locks this term for every enrolled student and publishes grades.</p>
    <?php if (!$canSubmit && $submitBlockReason !== ''): ?>
    <p class="notice" role="status"><?=e($submitBlockReason)?></p>
    <?php endif; ?>
    <form method="post" action="?page=assigned_blocks&amp;assignment_id=<?=(int)$assignmentId?>" <?=$canSubmit ? 'data-confirm="' . e($phase === 'midterm'
      ? 'Submit midterm grades for the whole roster? Midterm scores and items will lock.'
      : 'Submit finals grades for the whole roster? Final scores and items will lock, and overall grades will appear.') . '"' : ''?>>
      <?=csrf_field()?>
      <input type="hidden" name="form" value="submit_term">
      <input type="hidden" name="term" value="<?=e($phase === 'midterm' ? 'midterm' : 'final')?>">
      <div class="actions">
        <button type="submit" <?=$canSubmit ? '' : 'disabled'?>>
          <?= $phase === 'midterm' ? 'Submit midterm' : 'Submit finals' ?>
        </button>
      </div>
    </form>
  </section>
  <?php else: ?>
  <p class="notice" role="status">Both terms are submitted. Midterm, final, and overall grades below are read-only.</p>
  <?php endif; ?>
  <p class="muted table-scroll-hint"><?= $phase !== 'complete' ? 'Click a student row to enter or edit draft scores.' : 'Grades are locked for this subject.' ?></p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Number</th>
          <?php if ($phase === 'midterm' || $phase === 'final'): ?><th>Status</th><?php endif; ?>
          <?php if (!empty($submission['midterm_submitted_at'])): ?><th>Midterm</th><?php endif; ?>
          <?php if ($phase === 'complete'): ?><th>Final</th><th>Overall</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if (!$roster): ?>
        <tr><td colspan="5" class="empty">No students on the synced roster. Ask an administrator to match roster on the block.</td></tr>
      <?php else: foreach ($roster as $row):
        $canEnter = $phase !== 'complete';
        $rowHref = $canEnter
          ? '?page=submit_scores&assignment_id=' . (int)$assignmentId . '&student_id=' . (int)$row['student_id']
          : '';
        $label = (!empty($row['graded']) ? 'Edit scores for ' : 'Enter scores for ') . $row['last_name'] . ', ' . $row['first_name'];
      ?>
        <tr<?= $canEnter ? ' class="row-link" data-href="' . e($rowHref) . '" tabindex="0" aria-label="' . e($label) . '"' : '' ?>>
          <td><?=e($row['last_name'] . ', ' . $row['first_name'])?></td>
          <td><?=e($row['student_number'])?></td>
          <?php if ($phase === 'midterm' || $phase === 'final'): ?>
          <td><?php if (!empty($row['graded'])): ?><span class="badge badge-graded">Graded</span><?php else: ?><span class="badge badge-ungraded">Not graded</span><?php endif; ?></td>
          <?php endif; ?>
          <?php if (!empty($submission['midterm_submitted_at'])): ?>
          <td><?=e(format_grade($row['midterm_grade'] !== null ? (float)$row['midterm_grade'] : null))?></td>
          <?php endif; ?>
          <?php if ($phase === 'complete'): ?>
          <td><?=e(format_grade($row['final_grade'] !== null ? (float)$row['final_grade'] : null))?></td>
          <td><strong><?=e(format_grade($row['overall_grade'] !== null ? (float)$row['overall_grade'] : null))?></strong></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</dialog>
<?php endif; ?>

<?php elseif ($page === 'submit_scores'): ?>
<?php if ($assignment && $student): ?>
<dialog open class="wide-dialog" aria-labelledby="enter-scores-title" data-return-url="?page=assigned_blocks&amp;assignment_id=<?=(int)$assignmentId?>">
  <div class="bar">
    <div>
      <h2 id="enter-scores-title" tabindex="-1">Enter scores</h2>
      <p class="muted"><?=e($student['last_name'] . ', ' . $student['first_name'])?> · <?=e($student['student_number'])?> · <?=e($assignment['subject_code'])?> (<?=e($currentTerm === 'final' ? 'Finals' : 'Midterm')?>)<?php if ($rosterPosition): ?> · Student <?=(int)$rosterPosition?> of <?=(int)$rosterTotal?><?php endif; ?></p>
    </div>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close score form">×</button>
  </div>
  <?php errors($scoreErrors); ?>
  <p class="muted">Category weights: <?php
    $bits = [];
    foreach (grading_category_keys() as $key) {
        $bits[] = grading_category_labels()[$key] . ' ' . format_grade((float)$weights[$key]) . '%';
    }
    echo e(implode(' · ', $bits));
  ?>. Scores are drafts until you submit the term on the roster.</p>
  <div class="actions score-nav" style="margin-bottom:16px">
    <?php if ($prevStudentId): ?><a class="secondary" href="?page=submit_scores&amp;assignment_id=<?=(int)$assignmentId?>&amp;student_id=<?=(int)$prevStudentId?>" data-confirm-unsaved="Leave without saving draft scores for this student?">Previous student</a><?php endif; ?>
    <?php if ($nextStudentId): ?><a class="secondary" href="?page=submit_scores&amp;assignment_id=<?=(int)$assignmentId?>&amp;student_id=<?=(int)$nextStudentId?>" data-confirm-unsaved="Leave without saving draft scores for this student?">Next student</a><?php endif; ?>
  </div>
  <?php if (!$items): ?>
  <p class="muted">No score items for this term. Add items under <a href="?page=my_subjects&amp;assignment_id=<?=(int)$assignmentId?>">My Subjects</a> first.</p>
  <?php elseif ($locked): ?>
  <p class="muted">This term is locked. Scores cannot be edited.</p>
  <?php else: ?>
  <form method="post" action="?page=submit_scores&amp;assignment_id=<?=(int)$assignmentId?>&amp;student_id=<?=(int)$studentId?>" data-score-draft-form>
    <?=csrf_field()?>
    <?php foreach (grading_category_keys() as $cat):
      if (empty($itemsByCategory[$cat])) continue;
    ?>
    <fieldset class="form-section score-category">
      <legend><?=e(grading_category_labels()[$cat])?> <span class="muted">(<?=e(format_grade((float)$weights[$cat]))?>%)</span></legend>
      <div class="grid score-grid">
        <?php foreach ($itemsByCategory[$cat] as $item):
          $iid = (int)$item['id'];
          $val = $scoreMap[$iid] ?? '';
        ?>
        <div>
          <label for="score-<?=$iid?>"><?=e($item['title'])?> <span class="muted">(max <?=e(format_grade((float)$item['max_score']))?>)</span></label>
          <input id="score-<?=$iid?>" name="scores[<?=$iid?>]" type="number" min="0" max="<?=e((string)$item['max_score'])?>" step="0.01" value="<?=e($val === null || $val === '' ? '' : (string)$val)?>">
        </div>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit">Save draft scores</button>
      <?php if ($nextStudentId): ?><button type="submit" name="save_next" value="1" class="secondary">Save and next student</button><?php endif; ?>
      <a class="secondary" href="?page=assigned_blocks&amp;assignment_id=<?=(int)$assignmentId?>" data-confirm-unsaved="Leave without saving draft scores for this student?">Cancel</a>
    </div>
  </form>
  <?php endif; ?>
</dialog>
<?php else: ?>
<dialog open class="wide-dialog" aria-labelledby="enter-scores-missing" data-return-url="?page=assigned_blocks">
  <div class="bar">
    <h2 id="enter-scores-missing" tabindex="-1">Enter scores</h2>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button>
  </div>
  <p class="muted">Choose a student from an assigned block roster.</p>
</dialog>
<?php endif; ?>
<?php endif; ?>
