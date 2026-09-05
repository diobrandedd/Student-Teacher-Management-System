<div class="bar">
  <div><h1>Blocks</h1><p class="muted">Group students into a block, then assign teachers by subject. Subject enrollments update only when you save or match a subject to the current students.</p></div>
  <a class="button" href="?page=blocks&amp;add=1#block-form">Add block</a>
</div>
<?php if ($blockMissing): errors($blockErrors); ?>
  <a href="?page=blocks">Return to Blocks</a>
<?php elseif ($showBlockForm): ?>
<dialog open class="wide-dialog" id="block-form" aria-labelledby="block-form-title" data-return-url="?page=blocks">
  <div class="bar"><h2 id="block-form-title"><?=$blockId ? 'Edit block' : 'Add block'?></h2><button type="button" class="icon-button secondary" data-close-dialog aria-label="Close block form">×</button></div>
  <?php errors($blockErrors); ?>

  <form method="post" action="?page=blocks<?=$blockId ? '&amp;edit=' . $blockId : '&amp;add=1'?>#block-form">
    <?=csrf_field()?>
    <input type="hidden" name="form" value="block">
    <fieldset class="form-section">
      <legend>Block details</legend>
      <div class="grid">
        <div><label for="block-name">Block name</label><input id="block-name" name="name" required maxlength="100" value="<?=e($blockInput['name'])?>" placeholder="For example, Block 1"></div>
      </div>
    </fieldset>
    <div class="actions"><button><?=$blockId ? 'Save block' : 'Create block'?></button><button type="button" class="secondary" data-close-dialog>Cancel</button></div>
  </form>

  <?php if ($blockId): ?>
  <section class="form-section students-panel" id="block-students" aria-labelledby="block-students-title">
    <div class="bar">
      <div>
        <h3 id="block-students-title">Students</h3>
        <p class="muted"><?=count($rosterStudents)?> <?=count($rosterStudents)===1?'student':'students'?> in this block. Subject enrollments do not change automatically when you add or remove students.</p>
      </div>
      <?php if (!$assigningStudents): ?>
      <a class="button" href="?page=blocks&amp;edit=<?=(int)$blockId?>&amp;assign=1#block-students">Assign student to this block</a>
      <?php else: ?>
      <a class="button secondary" href="?page=blocks&amp;edit=<?=(int)$blockId?>#block-students">Done assigning</a>
      <?php endif; ?>
    </div>

    <?php if ($assigningStudents): ?>
    <div class="assign-student-panel">
      <h4>Assign student to this block</h4>
      <p class="muted">Search by name or student number (at least 2 characters). Choose <strong>Assign</strong> on a match — the whole school is never listed at once.</p>
      <?php if ($studentCatalogCount === 0): ?>
      <p class="empty-state">No students yet. Add student records before assigning them to a block.</p>
      <?php else: ?>
      <form class="search roster-search" method="get" action="index.php" role="search">
        <input type="hidden" name="page" value="blocks">
        <input type="hidden" name="edit" value="<?=(int)$blockId?>">
        <input type="hidden" name="assign" value="1">
        <label class="sr-only" for="block-student-search">Search students to assign</label>
        <input id="block-student-search" type="search" name="student_q" value="<?=e($studentSearch)?>" placeholder="Search student name or number" minlength="2" required autocomplete="off">
        <button>Find</button>
        <?php if ($studentSearch !== ''): ?><a href="?page=blocks&amp;edit=<?=(int)$blockId?>&amp;assign=1#block-students">Clear</a><?php endif; ?>
      </form>
      <?php if ($studentSearchTooShort): ?>
      <p class="muted" role="status">Enter at least 2 characters to search.</p>
      <?php elseif ($studentSearch !== ''): ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th scope="col">Student</th><th scope="col">Program</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
          <tbody>
            <?php foreach ($studentSearchResults as $student): ?>
            <tr>
              <td><strong><?=e($student['last_name'] . ', ' . $student['first_name'])?></strong><br><span class="muted"><?=e($student['student_number'])?></span></td>
              <td><?=e($student['course'] ?? 'No course')?> · <?=e(college_year_label($student['year_level'] ?? ''))?></td>
              <td>
                <form method="post" action="?page=blocks&amp;edit=<?=(int)$blockId?>&amp;assign=1#block-students">
                  <?=csrf_field()?>
                  <input type="hidden" name="form" value="assign_student">
                  <input type="hidden" name="student_id" value="<?=(int)$student['id']?>">
                  <button type="submit">Assign</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$studentSearchResults): ?>
            <tr><td colspan="3" class="empty-state"><strong>No matching students</strong><p>Try another name or number, or that student may already be in this block.</p></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table>
        <thead><tr><th scope="col">Student</th><th scope="col">Program</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($rosterStudents as $student): ?>
          <tr>
            <td><strong><?=e($student['last_name'] . ', ' . $student['first_name'])?></strong><br><span class="muted"><?=e($student['student_number'])?><?=$student['is_active']?'':' · Inactive'?></span></td>
            <td><?=e($student['course'] ?? 'No course')?> · <?=e(college_year_label($student['year_level'] ?? ''))?></td>
            <td>
              <form method="post" action="?page=blocks&amp;edit=<?=(int)$blockId?>#block-students">
                <?=csrf_field()?>
                <input type="hidden" name="form" value="remove_student">
                <input type="hidden" name="student_id" value="<?=(int)$student['id']?>">
                <button type="submit" class="secondary" data-confirm="Remove <?=e($student['last_name'] . ', ' . $student['first_name'])?> from this block?">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$rosterStudents): ?>
          <tr><td colspan="3" class="empty-state"><strong>No students in this block yet</strong><p>Use <strong>Assign student to this block</strong> to add the first student.</p></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="form-section assignment-panel" aria-labelledby="assignment-title">
    <h3 id="assignment-title">Subject assignments</h3>
    <p class="muted">Each row is one teacher teaching one subject in this block. Saving or matching a subject enrolls the current students (<?=(int)$memberCount?> student<?=$memberCount===1?'':'s'?>).</p>
    <?php if (!empty($assignmentsNeedResync)): ?>
    <div class="notice" role="status">Students in this block and subject enrollments differ for at least one subject. Use <strong>Match roster</strong> on those rows so enrollments catch up.</div>
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
    <p class="muted">No subjects assigned yet. Assign students above, then add a subject here.</p>
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
        <button <?=!$teachers?'disabled':''?>><?=$editAssignmentId ? 'Save and enroll current students' : 'Assign and enroll current students'?></button>
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
      $editUrl = '?page=blocks&edit=' . (int)$block['id'] . '#block-form';
      $studentsUrl = '?page=blocks&edit=' . (int)$block['id'] . '#block-students';
    ?>
    <tr class="row-link" data-href="<?=e($editUrl)?>" tabindex="0" aria-label="Open <?=e($block['name'])?>">
      <td><strong><?=e($block['name'])?></strong></td>
      <td>
        <?php if ($visibleChips): ?>
        <ul class="subject-chips">
          <?php foreach ($visibleChips as $chip): ?><li><?=e($chip)?></li><?php endforeach; ?>
          <?php if ($extraChips > 0): ?><li class="subject-chip-more">+<?=$extraChips?> more</li><?php endif; ?>
        </ul>
        <?php else: ?><span class="muted">No subjects yet</span><?php endif; ?>
      </td>
      <td><a href="<?=e($studentsUrl)?>" aria-label="View <?=(int)$block['student_count']?> students in <?=e($block['name'])?>"><?=(int)$block['student_count']?></a></td>
      <td><a href="<?=e($editUrl)?>" aria-label="Edit <?=e($block['name'])?>">Edit</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$blocks): ?><tr><td colspan="4" class="empty-state"><strong><?=$blockSearch!==''?'No matching blocks':'No blocks yet'?></strong><p><?=$blockSearch!==''?'Try another teacher, subject, or block name.':'Add a block, assign students, then assign teachers and subjects.'?></p></td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php if ($blockPages > 1): ?><nav aria-label="Block pages"><span>Page <?=$blockPage?> of <?=$blockPages?></span><?php if ($blockPage > 1): ?><a href="?<?=e(http_build_query(['page'=>'blocks','q'=>$blockSearch,'teacher_id'=>$teacherFilter,'p'=>$blockPage-1]))?>">Previous</a><?php endif; ?><?php if ($blockPage < $blockPages): ?><a href="?<?=e(http_build_query(['page'=>'blocks','q'=>$blockSearch,'teacher_id'=>$teacherFilter,'p'=>$blockPage+1]))?>">Next</a><?php endif; ?></nav><?php endif; ?>
