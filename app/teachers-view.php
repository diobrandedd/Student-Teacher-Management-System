<div class="bar">
  <div><h1>Teachers</h1><p class="muted">Create teacher profiles here. Username is auto-built from last name + first initial; a one-time temporary password is shown after create (must change on first sign-in).</p></div>
  <?php if ($canManage): ?><a class="button" href="?page=teachers&amp;add=1">Add teacher</a><?php endif; ?>
</div>
<?php
$teacherForm = $editTeacher ?: ($showTeacherCreate ? $createInput : null);
$isCreate = $showTeacherCreate && !$editTeacher;
if ($teacherForm && ($canManage || $editTeacher)):
?>
<dialog open class="wide-dialog" aria-labelledby="teacher-editor-title" data-return-url="?page=teachers">
  <div class="bar">
    <h2 id="teacher-editor-title"><?=$isCreate ? 'Add teacher' : ($canManage ? 'Edit teacher' : 'Teacher details')?></h2>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close teacher form">×</button>
  </div>
  <?php errors($teacherErrors); ?>
  <?php if ($canManage): ?>
  <form method="post" action="?page=teachers&amp;<?=$isCreate ? 'add=1' : 'edit=' . (int)$editTeacher['id']?>">
    <?=csrf_field()?>
    <fieldset class="form-section">
      <legend>Identity</legend>
      <div class="grid">
        <div><label for="teacher-last_name">Last name</label><input id="teacher-last_name" name="last_name" required maxlength="80" value="<?=e((string)($teacherForm['last_name'] ?? ''))?>" autocomplete="family-name"></div>
        <div><label for="teacher-first_name">First name</label><input id="teacher-first_name" name="first_name" required maxlength="80" value="<?=e((string)($teacherForm['first_name'] ?? ''))?>" autocomplete="given-name"></div>
        <div><label for="teacher-middle_name">Middle name <span class="muted">(optional)</span></label><input id="teacher-middle_name" name="middle_name" maxlength="80" value="<?=e((string)($teacherForm['middle_name'] ?? ''))?>" autocomplete="additional-name"></div>
      </div>
    </fieldset>
    <fieldset class="form-section">
      <legend>Contact</legend>
      <div class="grid">
        <div><label for="teacher-email">Email</label><input type="email" id="teacher-email" name="email" required maxlength="190" value="<?=e((string)($teacherForm['email'] ?? ''))?>"></div>
        <div><label for="teacher-phone">Contact number <span class="muted">(optional)</span></label><input id="teacher-phone" name="phone" maxlength="20" value="<?=e((string)($teacherForm['phone'] ?? ''))?>"></div>
        <div><label for="teacher-address">Address <span class="muted">(optional)</span></label><input id="teacher-address" name="address" maxlength="255" value="<?=e((string)($teacherForm['address'] ?? ''))?>"></div>
      </div>
    </fieldset>
    <fieldset class="form-section">
      <legend>Assignment</legend>
      <div class="grid">
        <div>
          <label for="teacher-department">Department</label>
          <select id="teacher-department" name="department_id">
            <option value="">Not assigned</option>
            <?php foreach ($departmentOptions as $option): ?>
              <option value="<?=$option['id']?>" <?=((string)($teacherForm['department_id'] ?? '') === (string)$option['id']) ? 'selected' : ''?>><?=e($option['name'])?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!$departmentOptions): ?><p class="muted">Add a department in Departments first.</p><?php endif; ?>
        </div>
        <?php if (!$isCreate): ?>
        <div>
          <p class="muted" style="margin-top:28px"><?=(int)$editTeacher['block_count']?> assigned <?=(int)$editTeacher['block_count']===1?'block':'blocks'?>. <a href="?<?=e(http_build_query(['page'=>'blocks','teacher_id'=>$editTeacher['id']]))?>">Open blocks for this teacher</a></p>
        </div>
        <?php endif; ?>
      </div>
    </fieldset>
    <fieldset class="form-section">
      <legend>Teacher login</legend>
      <p class="muted">Username is built automatically from last name + first initial (for example Delos Santos, Brent → <strong>Delossantos_B</strong>). A one-time temporary password is shown after create.</p>
      <div class="grid">
        <div>
          <span class="label-text">Username</span>
          <p class="username-preview"><strong data-username-preview><?php
            $preview = trim((string)($teacherForm['username'] ?? ''));
            if ($preview === '') $preview = login_username_base((string)($teacherForm['last_name'] ?? ''), (string)($teacherForm['first_name'] ?? ''));
            echo e($preview !== '' ? $preview : '—');
          ?></strong></p>
        </div>
        <?php if (!$isCreate): ?>
        <div>
          <label><input class="checkbox" type="checkbox" name="is_active" value="1" <?=!empty($editTeacher['is_active']) ? 'checked' : ''?>> Account is active</label>
          <p class="muted">Uncheck to deactivate without deleting. The teacher cannot sign in while inactive.</p>
        </div>
        <?php endif; ?>
      </div>
    </fieldset>
    <div class="actions"><button><?=$isCreate?'Create teacher':'Save teacher'?></button><?php if (!$isCreate): ?><button type="submit" class="secondary" name="reset_temp_password" value="1">Reset temporary password</button><?php endif; ?><button type="button" class="secondary" data-close-dialog>Cancel</button></div>
  </form>
  <?php elseif ($editTeacher): ?>
  <fieldset class="form-section"><legend>Identity</legend>
    <p><strong><?=e(teacher_display_name($editTeacher))?></strong></p>
  </fieldset>
  <fieldset class="form-section"><legend>Contact</legend>
    <p><?=e((string)$editTeacher['email'])?><?=($editTeacher['phone'] ?? '') !== '' ? ' · ' . e((string)$editTeacher['phone']) : ''?></p>
    <?php if (($editTeacher['address'] ?? '') !== ''): ?><p><?=e((string)$editTeacher['address'])?></p><?php endif; ?>
  </fieldset>
  <fieldset class="form-section"><legend>Assignment</legend>
    <p><?=e((string)($editTeacher['department_name'] ?? 'No department assigned'))?> · <?=(int)$editTeacher['block_count']?> blocks</p>
    <div class="actions">
      <a class="button" href="?<?=e(http_build_query(['page'=>'blocks','teacher_id'=>$editTeacher['id']]))?>">View blocks</a>
      <button type="button" class="secondary" data-close-dialog>Close</button>
    </div>
  </fieldset>
  <?php endif; ?>
</dialog>
<?php endif; ?>
<form class="search" method="get" role="search">
  <input type="hidden" name="page" value="teachers">
  <label class="sr-only" for="teacher-search">Search teachers by name or email</label>
  <input type="search" id="teacher-search" name="q" value="<?=e($teacherSearch)?>" placeholder="Search teacher name or email">
  <button>Search</button>
  <?php if ($teacherSearch !== ''): ?><a href="?page=teachers">Clear search</a><?php endif; ?>
</form>
<p class="muted"><?=$teacherTotal?> <?=$teacherTotal===1?'teacher':'teachers'?><?=$teacherSearch!==''?' matching “'.e($teacherSearch).'”':''?>. Click a row to <?=$canManage?'edit':'view'?>.</p>
<div class="table-wrap"><table>
  <thead><tr><th scope="col">Teacher</th><th scope="col">Email</th><th scope="col">Department</th><th scope="col">Status</th><th scope="col">Blocks</th></tr></thead>
  <tbody>
  <?php foreach ($teacherRows as $teacher): ?>
    <?php $label = teacher_display_name($teacher); ?>
    <tr class="row-link" data-href="?page=teachers&amp;edit=<?=(int)$teacher['id']?>" tabindex="0" aria-label="<?=e(($canManage?'Edit':'View').' '.$label)?>">
      <td><strong><?=e($label)?></strong><?php if (($teacher['phone'] ?? '') !== ''): ?><br><span class="muted"><?=e((string)$teacher['phone'])?></span><?php endif; ?></td>
      <td><?=e($teacher['email'])?></td>
      <td><?=e($teacher['department_name']??'Not assigned')?></td>
      <td><span class="badge<?=$teacher['is_active']?'':' badge-muted'?>"><?=$teacher['is_active']?'Active':'Deactivated'?></span></td>
      <td><?=$teacher['block_count']?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$teacherRows): ?><tr><td colspan="5" class="empty-state"><strong><?=$teacherSearch!==''?'No matching teachers':'No teachers yet'?></strong><p><?=$teacherSearch!==''?'Try another name or email.':'Teacher/Staff accounts appear here once an administrator creates them.'?></p><?php if($canManage && $teacherSearch===''):?><a href="?page=teachers&amp;add=1">Add teacher</a><?php endif;?></td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php if ($teacherPages>1): ?><nav class="pager" aria-label="Teacher pages"><span>Page <?=$teacherPage?> of <?=$teacherPages?></span><?php if ($teacherPage>1): ?><a href="?<?=e(http_build_query(['page'=>'teachers','q'=>$teacherSearch,'p'=>$teacherPage-1]))?>">Previous</a><?php endif; ?><?php if ($teacherPage<$teacherPages): ?><a href="?<?=e(http_build_query(['page'=>'teachers','q'=>$teacherSearch,'p'=>$teacherPage+1]))?>">Next</a><?php endif; ?></nav><?php endif; ?>
