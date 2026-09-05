<div class="bar">
  <div><h1>Blocks</h1><p class="muted">Group students into a block, then assign teachers by subject. Enrollments update only when you save or match a subject to the current roster.</p></div>
  <a class="button" href="?page=blocks&amp;add=1#block-form">Add block</a>
</div>
<?php if ($blockMissing): errors($blockErrors); ?>
  <a href="?page=blocks">Return to Blocks</a>
<?php elseif ($showBlockForm): ?>
<dialog open class="wide-dialog" id="block-form" aria-labelledby="block-form-title" data-return-url="?page=blocks">
  <div class="bar"><h2 id="block-form-title"><?=$blockId ? 'Edit block' : 'Add block'?></h2><button type="button" class="icon-button secondary" data-close-dialog aria-label="Close block form">×</button></div>
  <?php errors($blockErrors); ?>
  <form method="post" action="?page=blocks<?=$blockId ? '&amp;edit=' . $blockId : '&amp;add=1'?>#block-form" id="membership-form">
    <?=csrf_field()?>
    <input type="hidden" name="form" value="block">
    <fieldset class="form-section">
      <legend>1. Membership</legend>
      <p class="muted">Name the block and choose its students. Saving membership does not change subject enrollments.</p>
      <div class="grid">
        <div><label for="block-name">Block name</label><input id="block-name" name="name" required maxlength="100" value="<?=e($blockInput['name'])?>" placeholder="For example, BSIT 1-A"></div>
      </div>
      <div class="student-picker">
        <p id="membership-help" class="muted">Select at least one student. Students can belong to multiple blocks.</p>
        <label for="block-student-filter">Find students</label>
        <input id="block-student-filter" type="search" placeholder="Search student name or number" data-filter-students autocomplete="off">
        <p class="muted" data-selection-count aria-live="polite"><?=count($selectedStudents)?> selected</p>
        <div class="student-options">
          <?php foreach ($blockStudents as $student): ?>
          <label class="student-option" data-student-option>
            <input type="checkbox" name="student_ids[]" value="<?=$student['id']?>" <?=in_array((int)$student['id'], $selectedStudents, true)?'checked':''?> aria-describedby="membership-help">
            <span><strong><?=e($student['last_name'] . ', ' . $student['first_name'])?></strong><span class="muted"><?=e($student['student_number'])?> · <?=e($student['course'] ?? 'No course')?> · Year <?=e((string)($student['year_level'] ?? ''))?><?=$student['is_active']?'':' · Inactive student (existing member)'?></span></span>
          </label>
          <?php endforeach; ?>
          <?php if (!$blockStudents): ?><p class="empty-state">No students yet. Add student records before creating a block.</p><?php endif; ?>
          <p class="empty-state" data-no-student-results hidden>No students match your search.</p>
        </div>
      </div>
    </fieldset>
    <div class="actions"><button <?=!$blockStudents?'disabled':''?>><?=$blockId?'Save membership':'Create block'?></button><button type="button" class="secondary" data-close-dialog>Cancel</button></div>
  </form>

  <?php if ($blockId): ?>
  <section class="form-section assignment-panel" aria-labelledby="assignment-title">
    <h3 id="assignment-title">2. Subject assignments</h3>
    <p class="muted">Each row is one teacher teaching one subject in this block. Saving or matching a subject enrolls the current roster (<?=(int)$memberCount?> student<?=$memberCount===1?'':'s'?>).</p>
    <?php if (!empty($assignmentsNeedResync)): ?>
    <div class="notice" role="status">Roster and enrollments differ for at least one subject. Use <strong>Match roster</strong> on those rows so enrollments catch up.</div>
    <?php endif; ?>
    <?php errors($assignmentErrors); ?>
    <?php if (!$teachers): ?><div class="notice">No active teachers are available. An administrator must create a Teacher/Staff account first.</div><?php endif; ?>

    <?php if ($assignments): ?>
    <div class="table-wrap assignment-table">
      <table>
        <thead><tr><th scope="col">Code</th><th scope="col">Subject</th><th scope="col">Teacher</th><th scope="col">Enrolled</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($assignments as $assignment): ?>
          <tr<?php if ($assignment['row_class'] !== '') echo ' class="' . e($assignment['row_class']) . '"'; ?>>
            <td><strong><?=e($assignment['subject_code'])?></strong></td>
            <td><?=e($assignment['subject_name'])?></td>
            <td><?=e($assignment['teacher_name'])?><?php if (!$assignment['teacher_active'] || $assignment['teacher_role'] !== 'staff'): ?> <span class="badge">Unavailable</span><?php endif; ?></td>
            <td><?=e((string)$assignment['enrollment_count'])?></td>
            <td><?php if (!empty($assignment['needs_resync'])): ?><span class="badge badge-warn">Roster changed</span><?php else: ?><span class="muted">Matched</span><?php endif; ?></td>
            <td class="assignment-actions">
              <form method="post" action="?page=blocks&amp;edit=<?=e((string)$blockId)?>#assignment-title">
                <?=csrf_field()?>
                <input type="hidden" name="form" value="assignment">
                <input type="hidden" name="assignment_id" value="<?=e((string)$assignment['id'])?>">
                <?php if (!empty($assignment['needs_resync'])): ?>
                <button type="submit" name="assignment_action" value="resync" data-confirm="<?=e($assignment['match_confirm'])?>">Match roster</button>
                <?php endif; ?>
                <button type="submit" name="assignment_action" value="edit" class="secondary">Edit</button>
                <?php if (empty($assignment['needs_resync'])): ?>
                <button type="submit" name="assignment_action" value="resync" class="secondary" data-confirm="<?=e($assignment['match_confirm'])?>">Match roster</button>
                <?php endif; ?>
                <button type="submit" name="assignment_action" value="delete" class="danger" data-confirm="<?=e($assignment['remove_confirm'])?>">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="muted">No subjects assigned yet. Add one below after membership is set.</p>
    <?php endif; ?>

    <form method="post" action="?page=blocks&amp;edit=<?=$blockId?>#assignment-title" class="assignment-form">
      <?=csrf_field()?>
      <input type="hidden" name="form" value="assignment">
      <input type="hidden" name="assignment_action" value="save">
      <?php if ($editAssignmentId): ?><input type="hidden" name="assignment_id" value="<?=(int)$editAssignmentId?>"><?php endif; ?>
      <h4><?=$editAssignmentId ? 'Edit subject assignment' : 'Add subject assignment'?></h4>
      <div class="grid">
        <div>
          <label for="assignment-teacher">Teacher</label>
          <select id="assignment-teacher" name="teacher_id" required>
            <option value="">Select a teacher</option>
            <?php foreach ($teachers as $teacher): ?>
            <option value="<?=$teacher['id']?>" <?=(string)$assignmentInput['teacher_id']===(string)$teacher['id']?'selected':''?>><?=e($teacher['full_name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="assignment-code">Subject code</label>
          <input id="assignment-code" name="subject_code" required maxlength="30" value="<?=e($assignmentInput['subject_code'])?>" placeholder="For example, IT101" spellcheck="false" autocapitalize="characters">
        </div>
        <div>
          <label for="assignment-name">Subject name</label>
          <input id="assignment-name" name="subject_name" required maxlength="150" value="<?=e($assignmentInput['subject_name'])?>" placeholder="For example, Introduction to Computing">
        </div>
      </div>
      <div class="actions">
        <button <?=!$teachers?'disabled':''?>><?=$editAssignmentId ? 'Save and enroll current roster' : 'Assign and enroll current roster'?></button>
        <?php if ($editAssignmentId): ?><a class="button secondary" href="?page=blocks&amp;edit=<?=$blockId?>#assignment-title">Cancel edit</a><?php endif; ?>
      </div>
    </form>
  </section>
  <?php endif; ?>
</dialog>
<?php endif; ?>
<form class="search" method="get" role="search">
  <input type="hidden" name="page" value="blocks">
  <?php if ($teacherFilter): ?><input type="hidden" name="teacher_id" value="<?=$teacherFilter?>"><?php endif; ?>
  <label class="sr-only" for="block-search">Search by teacher, subject, or block name</label>
  <input type="search" id="block-search" name="q" value="<?=e($blockSearch)?>" placeholder="Search teacher, subject, or block">
  <button>Search</button>
  <?php if ($blockSearch !== '' || $teacherFilter): ?><a href="?page=blocks">Clear search</a><?php endif; ?>
</form>
<?php if ($teacherFilter): ?><p class="muted">Showing blocks with a subject taught by the selected teacher. <a href="?page=teachers">Back to Teachers</a></p><?php endif; ?>
<p class="muted"><?=$blockTotal?> <?=$blockTotal===1?'block':'blocks'?><?=$blockSearch!==''?' matching "'.e($blockSearch).'"':''?></p>
<div class="table-wrap"><table>
  <thead><tr><th scope="col">Block</th><th scope="col">Subjects / Teachers</th><th scope="col">Students</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
  <tbody>
    <?php foreach ($blocks as $block): ?>
    <?php
      $chips = [];
      if (!empty($block['subject_summary'])) {
        foreach (explode(' | ', (string)$block['subject_summary']) as $piece) {
          $chips[] = $piece;
        }
      }
      $chipLimit = 2;
      $visibleChips = array_slice($chips, 0, $chipLimit);
      $extraChips = max(0, count($chips) - $chipLimit);
    ?>
    <tr>
      <td><strong><?=e($block['name'])?></strong></td>
      <td>
        <?php if ($visibleChips): ?>
        <ul class="subject-chips">
          <?php foreach ($visibleChips as $chip): ?><li><?=e($chip)?></li><?php endforeach; ?>
          <?php if ($extraChips > 0): ?><li class="subject-chip-more">+<?=$extraChips?> more</li><?php endif; ?>
        </ul>
        <?php else: ?><span class="muted">No subjects yet</span><?php endif; ?>
      </td>
      <td><?=$block['student_count']?></td>
      <td><a href="?page=blocks&amp;edit=<?=$block['id']?>#block-form" aria-label="View or edit <?=e($block['name'])?>">View / Edit</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$blocks): ?><tr><td colspan="4" class="empty-state"><strong><?=$blockSearch!==''?'No matching blocks':'No blocks yet'?></strong><p><?=$blockSearch!==''?'Try another teacher, subject, or block name.':'Add a block, select its students, then assign teachers and subjects.'?></p></td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php if ($blockPages > 1): ?><nav aria-label="Block pages"><span>Page <?=$blockPage?> of <?=$blockPages?></span><?php if ($blockPage > 1): ?><a href="?<?=e(http_build_query(['page'=>'blocks','q'=>$blockSearch,'teacher_id'=>$teacherFilter,'p'=>$blockPage-1]))?>">Previous</a><?php endif; ?><?php if ($blockPage < $blockPages): ?><a href="?<?=e(http_build_query(['page'=>'blocks','q'=>$blockSearch,'teacher_id'=>$teacherFilter,'p'=>$blockPage+1]))?>">Next</a><?php endif; ?></nav><?php endif; ?>
