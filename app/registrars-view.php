<?php
$listQs = $registrarListQuery;
$returnList = '?' . http_build_query($listQs());
$hasSearch = $registrarSearch !== '';
?>
<div class="bar">
  <div>
    <h1>Registrars</h1>
    <p class="muted">Registrar staff with segmented duties: Enrollments, Blocking, and/or Teachers. Username is auto-built from last name + first initial on create; a one-time temporary password is shown after save.</p>
  </div>
  <a class="button" href="?<?=e(http_build_query($listQs(['add' => 1, 'p' => 1])))?>">Add registrar</a>
</div>
<?php
$registrarForm = $editRegistrar ?: ($showRegistrarCreate ? $createInput : null);
$isCreate = $showRegistrarCreate && !$editRegistrar;
if ($registrarForm):
  $permEnroll = !empty($registrarForm['can_enrollments']);
  $permStudents = !empty($registrarForm['can_view_students']);
  $permBlocking = !empty($registrarForm['can_blocking']);
  $permTeachers = !empty($registrarForm['can_assign_teachers']);
?>
<dialog open class="wide-dialog" aria-labelledby="registrar-editor-title" data-return-url="<?=e($returnList)?>">
  <div class="bar">
    <h2 id="registrar-editor-title"><?=$isCreate ? 'Add registrar' : 'Edit registrar'?></h2>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close registrar form">×</button>
  </div>
  <?php errors($registrarErrors); ?>
  <form method="post" action="?<?=e(http_build_query($listQs($isCreate ? ['add' => 1] : ['edit' => (int)$editRegistrar['id']])))?>">
    <?=csrf_field()?>
    <?php if ($isCreate): ?>
    <fieldset class="form-section">
      <legend>Identity</legend>
      <div class="grid">
        <div><label for="registrar-last_name">Last name</label><input id="registrar-last_name" name="last_name" required maxlength="80" value="<?=e((string)($registrarForm['last_name'] ?? ''))?>" autocomplete="family-name"></div>
        <div><label for="registrar-first_name">First name</label><input id="registrar-first_name" name="first_name" required maxlength="80" value="<?=e((string)($registrarForm['first_name'] ?? ''))?>" autocomplete="given-name"></div>
        <div><label for="registrar-middle_name">Middle name <span class="muted">(optional)</span></label><input id="registrar-middle_name" name="middle_name" maxlength="80" value="<?=e((string)($registrarForm['middle_name'] ?? ''))?>" autocomplete="additional-name"></div>
      </div>
    </fieldset>
    <fieldset class="form-section">
      <legend>Contact</legend>
      <div class="grid">
        <div><label for="registrar-email">Email</label><input type="email" id="registrar-email" name="email" required maxlength="190" value="<?=e((string)($registrarForm['email'] ?? ''))?>"></div>
      </div>
    </fieldset>
    <fieldset class="form-section">
      <legend>Registrar login</legend>
      <p class="muted">Username is built automatically from last name + first initial (for example Garcia, Rosa → <strong>Garcia_R</strong>). A one-time temporary password is shown after create.</p>
      <div class="grid">
        <div>
          <span class="label-text">Username</span>
          <p class="username-preview"><strong data-username-preview><?php
            $preview = trim((string)($registrarForm['username'] ?? ''));
            if ($preview === '') $preview = login_username_base((string)($registrarForm['last_name'] ?? ''), (string)($registrarForm['first_name'] ?? ''));
            echo e($preview !== '' ? $preview : '—');
          ?></strong></p>
        </div>
      </div>
    </fieldset>
    <?php else: ?>
    <fieldset class="form-section">
      <legend>Account</legend>
      <div class="grid">
        <div><label for="registrar-full_name">Full name</label><input id="registrar-full_name" name="full_name" required maxlength="150" value="<?=e((string)($registrarForm['full_name'] ?? ''))?>" autocomplete="name"></div>
        <div><label for="registrar-email">Email</label><input type="email" id="registrar-email" name="email" required maxlength="190" value="<?=e((string)($registrarForm['email'] ?? ''))?>"></div>
        <div>
          <span class="label-text">Username</span>
          <p class="username-preview"><strong><?=e((string)($editRegistrar['username'] ?? ''))?></strong></p>
          <p class="muted">Username stays the same after create.</p>
        </div>
        <div>
          <label><input class="checkbox" type="checkbox" name="is_active" value="1" <?=!empty($editRegistrar['is_active']) ? 'checked' : ''?>> Account is active</label>
          <p class="muted">Uncheck to deactivate without deleting. The registrar cannot sign in while inactive.</p>
        </div>
      </div>
    </fieldset>
    <?php endif; ?>
    <fieldset class="form-section">
      <legend>Duties</legend>
      <p class="muted"><?=$isCreate ? 'New registrars start with no duties. Check only the areas this person should open.' : 'Only checked areas appear in this registrar’s navigation and can be opened.'?></p>
      <ul class="permission-list">
        <li>
          <label><input class="checkbox" type="checkbox" name="can_enrollments" value="1" <?=$permEnroll ? 'checked' : ''?>> Enrollments</label>
          <p class="muted">Review, approve, and reject enrollment applications.</p>
        </li>
        <li>
          <label><input class="checkbox" type="checkbox" name="can_view_students" value="1" <?=$permStudents ? 'checked' : ''?>> Students</label>
          <p class="muted">View and search student records (read-only).</p>
        </li>
        <li>
          <label><input class="checkbox" type="checkbox" name="can_blocking" value="1" <?=$permBlocking ? 'checked' : ''?>> Blocking</label>
          <p class="muted">Place unblocked active students into a block.</p>
        </li>
        <li>
          <label><input class="checkbox" type="checkbox" name="can_assign_teachers" value="1" <?=$permTeachers ? 'checked' : ''?>> Teachers</label>
          <p class="muted">Assign blocks and catalog subjects to teachers.</p>
        </li>
      </ul>
    </fieldset>
    <div class="actions">
      <button><?=$isCreate ? 'Create registrar' : 'Save registrar'?></button>
      <?php if (!$isCreate): ?><button type="submit" class="secondary" name="reset_temp_password" value="1">Reset temporary password</button><?php endif; ?>
      <button type="button" class="secondary" data-close-dialog>Cancel</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<section class="queue-tools" aria-label="Registrar filters">
  <nav class="status-tabs" aria-label="Registrar account status">
    <?php foreach (['active' => 'Active', 'inactive' => 'Deactivated', 'all' => 'All'] as $key => $label): ?>
    <a href="?<?=e(http_build_query($listQs(['status' => $key, 'p' => 1])))?>" <?=$registrarStatus===$key?'aria-current="page"':''?>><?=e($label)?> <span class="tab-count"><?=(int)($registrarStatusCounts[$key] ?? 0)?></span></a>
    <?php endforeach; ?>
  </nav>
  <form class="filter-panel filter-panel--queue filter-panel--simple" method="get" role="search">
    <input type="hidden" name="page" value="registrars">
    <input type="hidden" name="status" value="<?=e($registrarStatus)?>">
    <div class="filter-search">
      <label for="registrar-search">Search</label>
      <input type="search" id="registrar-search" name="q" value="<?=e($registrarSearch)?>" placeholder="Name, username, or email">
    </div>
    <div class="filter-actions">
      <button>Apply</button>
      <?php if ($hasSearch): ?><a class="button secondary" href="?<?=e(http_build_query($listQs(['q' => '', 'p' => 1])))?>">Clear</a><?php endif; ?>
    </div>
  </form>
  <?php
  $showStatusCol = $registrarStatus === 'all';
  $metaBits = [$registrarTotal . ' ' . ($registrarTotal === 1 ? 'registrar' : 'registrars')];
  if ($hasSearch) {
      $metaBits[] = 'filtered';
  }
  $metaBits[] = 'Page ' . $registrarPage . ' of ' . $registrarPages;
  ?>
  <p class="queue-meta"><?=e(implode(' · ', $metaBits))?> · Click a row to edit</p>
</section>
<div class="table-wrap queue-table">
  <table>
  <thead><tr><th scope="col">Registrar</th><th scope="col">Username / Email</th><th scope="col">Duties</th><?php if ($showStatusCol): ?><th scope="col">Status</th><?php endif; ?><th scope="col">Last login</th></tr></thead>
  <tbody>
  <?php foreach ($registrarRows as $row): ?>
    <tr class="row-link" data-href="?<?=e(http_build_query($listQs(['edit' => (int)$row['id']])))?>" tabindex="0" aria-label="Edit <?=e($row['full_name'])?>">
      <td><strong><?=e($row['full_name'])?></strong></td>
      <td><?=e($row['username'])?><br><span class="muted"><?=e($row['email'])?></span></td>
      <td>
        <?php if (!empty($row['duty_labels'])): ?>
        <ul class="subject-chips">
          <?php foreach ($row['duty_labels'] as $duty): ?><li><?=e($duty)?></li><?php endforeach; ?>
        </ul>
        <?php else: ?><span class="muted">None</span><?php endif; ?>
      </td>
      <?php if ($showStatusCol): ?><td><span class="badge<?=$row['is_active']?'':' badge-muted'?>"><?=$row['is_active']?'Active':'Deactivated'?></span></td><?php endif; ?>
      <td><?=e($row['last_login_at'] ?? 'Never')?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$registrarRows): ?><tr><td colspan="<?=$showStatusCol?5:4?>" class="empty-state"><strong><?=$hasSearch||$registrarStatus!=='active'?'No matching registrars':'No registrars yet'?></strong><p><?=$hasSearch?'Try another name, username, or email.':($registrarStatus==='inactive'?'No deactivated registrar accounts.':'Create a registrar account and assign duties for enrollments, blocking, or teachers.')?></p><?php if(!$hasSearch && $registrarStatus==='active'):?><a href="?<?=e(http_build_query($listQs(['add'=>1,'p'=>1])))?>">Add registrar</a><?php elseif($hasSearch||$registrarStatus!=='active'):?><a href="?<?=e(http_build_query(['page'=>'registrars','status'=>'active']))?>">Show active</a><?php endif;?></td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php if ($registrarPages>1): ?><nav class="pager" aria-label="Registrar pages"><span>Page <?=$registrarPage?> of <?=$registrarPages?></span><?php if ($registrarPage>1): ?><a href="?<?=e(http_build_query($listQs(['p'=>$registrarPage-1])))?>">Previous</a><?php endif; ?><?php if ($registrarPage<$registrarPages): ?><a href="?<?=e(http_build_query($listQs(['p'=>$registrarPage+1])))?>">Next</a><?php endif; ?></nav><?php endif; ?>
