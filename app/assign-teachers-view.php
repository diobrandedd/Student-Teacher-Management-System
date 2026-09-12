<?php
declare(strict_types=1);
$pageTitle = $isAdmin ? 'Assign teachers' : 'Teachers';
$showTeacherDialog = $selectedTeacher && !$editAssignmentId;
$showEditDialog = $selectedTeacher && $editAssignmentId && $editingAssignment;
?>
<div class="bar">
  <div>
    <h1><?=e($pageTitle)?></h1>
    <p class="muted">Select a teacher, then assign which blocks and subjects they teach.</p>
  </div>
</div>

<?php if ($showEditDialog): ?>
<dialog open aria-labelledby="edit-assignment-title" data-return-url="?<?=e($teacherReturnQs)?>">
  <form method="post" action="?page=assign_teachers&amp;teacher=<?=(int)$teacherId?>">
    <?=csrf_field()?>
    <input type="hidden" name="assignment_action" value="save">
    <input type="hidden" name="assignment_id" value="<?=(int)$editAssignmentId?>">
    <div class="bar">
      <div>
        <h2 id="edit-assignment-title" tabindex="-1">Edit assignment</h2>
        <p class="muted"><?=e($selectedTeacher['full_name'])?> · change the block and/or subject</p>
      </div>
      <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button>
    </div>
    <?php errors($assignmentErrors); ?>
    <div class="grid">
      <div class="searchable-select" data-searchable-select>
        <label for="edit-at-block">Block</label>
        <input type="search" id="edit-at-block-search" data-searchable-filter placeholder="Search block name" autocomplete="off" aria-controls="edit-at-block"<?=!$editBlocks?' disabled':''?>>
        <select id="edit-at-block" name="block_id" required data-searchable-target<?=!$editBlocks?' disabled':''?>>
          <option value="">Select a block</option>
          <?php foreach ($editBlocks as $block): ?>
          <option value="<?=(int)$block['id']?>" <?=(string)$assignmentInput['block_id']===(string)$block['id']?'selected':''?>><?=e(block_label($block))?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="edit-at-subject">Subject</label>
        <select id="edit-at-subject" name="subject_id" required<?=!$subjectOptions?' disabled':''?>>
          <option value="">Select a subject</option>
          <?php foreach ($subjectOptions as $subject): ?>
          <option value="<?=(int)$subject['id']?>" <?=(string)$assignmentInput['subject_id']===(string)$subject['id']?'selected':''?>><?=e(subject_label($subject))?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="actions">
      <button <?=(!$editBlocks || !$subjectOptions)?'disabled':''?>>Save changes</button>
      <button type="button" class="secondary" data-close-dialog>Cancel</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<?php if ($showTeacherDialog): ?>
<dialog open class="wide-dialog" aria-labelledby="assign-teachers-title" data-return-url="?<?=e($returnQs)?>">
  <div class="bar">
    <div>
      <h2 id="assign-teachers-title" tabindex="-1"><?=e($selectedTeacher['full_name'])?></h2>
      <p class="muted"><?=e((string)$selectedTeacher['email'])?></p>
    </div>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button>
  </div>

  <section class="form-section assignment-panel" aria-labelledby="assignment-title">
    <h3 id="assignment-title">Blocks for this teacher</h3>
    <p class="muted">Assign a block and subject. Students who join that block later are enrolled automatically.</p>
    <?php errors($assignmentErrors); ?>
    <?php if (!$subjectOptions): ?>
    <div class="notice">No subjects in the catalog.<?php if ($isAdmin): ?> <a href="?page=subjects&amp;add=1">Add a subject</a> first.<?php else: ?> Ask an administrator to add subjects.<?php endif; ?></div>
    <?php endif; ?>
    <?php if (!$allBlocks): ?>
    <div class="notice">No blocks yet.<?php if ($isAdmin): ?> <a href="?page=blocks&amp;add=1">Create a block</a> first.<?php else: ?> Ask an administrator to create blocks first.<?php endif; ?></div>
    <?php endif; ?>

    <?php if ($assignments): ?>
    <div class="table-wrap assignment-table">
      <table>
        <thead><tr><th scope="col">Block</th><th scope="col">Subject</th><th scope="col">Students</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($assignments as $assignment):
            $editUrl = '?' . http_build_query(array_filter([
              'page' => 'assign_teachers',
              'teacher' => $teacherId,
              'edit' => (int)$assignment['id'],
              'q' => $teacherSearch !== '' ? $teacherSearch : null,
              'p' => $teacherPage > 1 ? $teacherPage : null,
            ]));
          ?>
          <tr>
            <td><strong><?=e(block_label($assignment['block_name'], $assignment['block_year_level'] ?? null))?></strong><br><span class="muted"><?=(int)$assignment['block_student_count']?> in block</span></td>
            <td><strong><?=e($assignment['subject_code'])?></strong><br><span class="muted"><?=e($assignment['subject_name'])?></span></td>
            <td><?=e((string)$assignment['enrollment_count'])?></td>
            <td class="assignment-actions">
              <a class="button secondary" href="<?=e($editUrl)?>">Edit</a>
              <form method="post" action="?page=assign_teachers&amp;teacher=<?=(int)$teacherId?>">
                <?=csrf_field()?>
                <input type="hidden" name="assignment_id" value="<?=(int)$assignment['id']?>">
                <button type="submit" name="assignment_action" value="delete" class="danger" data-confirm="<?=e($assignment['remove_confirm'])?>">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="muted">Not assigned to any block yet. Choose a block and subject below.</p>
    <?php endif; ?>

    <form method="post" action="?page=assign_teachers&amp;teacher=<?=(int)$teacherId?>#assignment-title" class="assignment-form">
      <?=csrf_field()?>
      <input type="hidden" name="assignment_action" value="save">
      <h4>Assign to a block</h4>
      <div class="grid">
        <div class="searchable-select" data-searchable-select>
          <label for="at-block">Block</label>
          <input type="search" id="at-block-search" data-searchable-filter placeholder="Search block name" autocomplete="off" aria-controls="at-block"<?=!$availableBlocks?' disabled':''?>>
          <select id="at-block" name="block_id" required data-searchable-target<?=!$availableBlocks?' disabled':''?>>
            <option value="">Select a block</option>
            <?php foreach ($availableBlocks as $block): ?>
            <option value="<?=(int)$block['id']?>" <?=(string)$assignmentInput['block_id']===(string)$block['id']?'selected':''?>><?=e(block_label($block))?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($allBlocks && !$availableBlocks): ?>
          <p class="muted">This teacher is already on every block. Edit a row to change block or subject.</p>
          <?php endif; ?>
        </div>
        <div>
          <label for="at-subject">Subject</label>
          <select id="at-subject" name="subject_id" required<?=!$subjectOptions?' disabled':''?>>
            <option value="">Select a subject</option>
            <?php foreach ($subjectOptions as $subject): ?>
            <option value="<?=(int)$subject['id']?>" <?=(string)$assignmentInput['subject_id']===(string)$subject['id']?'selected':''?>><?=e(subject_label($subject))?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="actions">
        <button <?=(!$availableBlocks || !$subjectOptions)?'disabled':''?>>Assign to block</button>
      </div>
    </form>
  </section>
</dialog>
<?php elseif ($selectedTeacher && $assignmentErrors && !$showEditDialog): ?>
<div class="notice"><?php foreach ($assignmentErrors as $err): ?><p><?=e($err)?></p><?php endforeach; ?></div>
<?php endif; ?>

<section class="queue-tools" aria-label="Teacher search">
  <form method="get" class="filter-panel filter-panel--queue filter-panel--simple" role="search">
    <input type="hidden" name="page" value="assign_teachers">
    <div class="filter-search">
      <label for="at-teacher-q">Search</label>
      <input id="at-teacher-q" type="search" name="q" value="<?=e($teacherSearch)?>" placeholder="Search teacher name or email">
    </div>
    <div class="filter-actions">
      <button type="submit">Apply</button>
      <?php if ($teacherSearch !== ''): ?><a class="button secondary" href="?page=assign_teachers">Clear</a><?php endif; ?>
    </div>
  </form>
  <p class="queue-meta"><?=$teacherTotal?> <?=$teacherTotal===1?'teacher':'teachers'?> · Page <?=$teacherPage?> of <?=$teacherPages?> · Click a row to assign blocks</p>
</section>

<div class="table-wrap queue-table">
  <table>
    <thead>
      <tr>
        <th scope="col">Teacher</th>
        <th scope="col">Blocks</th>
        <th scope="col">Assignments</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$teacherRows): ?>
      <tr>
        <td colspan="3" class="empty-state">
          <strong><?=$teacherSearch!==''?'No matching teachers':'No active teachers yet'?></strong>
          <p><?=$teacherSearch!==''?'Try another name or email.':($isAdmin?'Create Teacher/Staff accounts under People → Teachers.':'Ask an administrator to create Teacher/Staff accounts first.')?></p>
          <?php if ($teacherSearch!==''): ?><a href="?page=assign_teachers">Clear search</a><?php elseif ($isAdmin): ?><a href="?page=teachers">Open Teachers directory</a><?php endif; ?>
        </td>
      </tr>
      <?php else: foreach ($teacherRows as $row):
        $openUrl = '?page=assign_teachers&teacher=' . (int)$row['id'] . ($teacherSearch!==''?'&q='.rawurlencode($teacherSearch):'') . ($teacherPage>1?'&p='.$teacherPage:'');
        $chips = $row['block_summary'] ? explode(' | ', (string)$row['block_summary']) : [];
        $visible = array_slice($chips, 0, 2);
        $extra = max(0, count($chips) - 2);
      ?>
      <tr class="row-link" data-href="<?=e($openUrl)?>" tabindex="0" aria-label="Assign blocks for <?=e($row['full_name'])?>">
        <td><strong><?=e($row['full_name'])?></strong><br><span class="muted"><?=e($row['email'])?></span></td>
        <td><?=(int)$row['assignment_count']?></td>
        <td>
          <?php if ($visible): ?>
          <ul class="subject-chips">
            <?php foreach ($visible as $chip): ?><li><?=e($chip)?></li><?php endforeach; ?>
            <?php if ($extra > 0): ?><li class="subject-chip-more">+<?=$extra?> more</li><?php endif; ?>
          </ul>
          <?php else: ?><span class="muted">No blocks yet</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php if ($teacherPages > 1): ?>
<nav class="pager" aria-label="Teacher pages">
  <span>Page <?=$teacherPage?> of <?=$teacherPages?></span>
  <?php if ($teacherPage > 1): ?><a href="?<?=e(http_build_query(['page'=>'assign_teachers','q'=>$teacherSearch,'p'=>$teacherPage-1]))?>">Previous</a><?php endif; ?>
  <?php if ($teacherPage < $teacherPages): ?><a href="?<?=e(http_build_query(['page'=>'assign_teachers','q'=>$teacherSearch,'p'=>$teacherPage+1]))?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
