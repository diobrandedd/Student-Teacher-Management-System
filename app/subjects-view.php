<?php
declare(strict_types=1);
?>
<div class="bar">
  <div>
    <h1>Subjects</h1>
    <p class="muted">Catalog of subject codes and titles used when assigning teachers to blocks.</p>
  </div>
  <a class="button" href="?page=subjects&amp;add=1">Add subject</a>
</div>

<?php if ($subjectShow): ?>
<dialog open aria-labelledby="subject-title" data-return-url="?page=subjects">
  <div class="bar">
    <h2 id="subject-title"><?=$subjectId ? 'Edit' : 'Add'?> subject</h2>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button>
  </div>
  <?php errors($subjectErrors); ?>
  <form method="post">
    <?=csrf_field()?>
    <div class="grid">
      <div>
        <label for="subject-code">Subject code</label>
        <input id="subject-code" name="code" maxlength="30" required value="<?=e($subjectCode)?>" placeholder="IT 411" spellcheck="false" autocapitalize="characters">
      </div>
      <div>
        <label for="subject-title-field">Subject title</label>
        <input id="subject-title-field" name="title" maxlength="150" required value="<?=e($subjectTitle)?>" placeholder="Systems Analysis and Design">
      </div>
    </div>
    <div class="actions">
      <button>Save subject</button>
      <button type="button" class="secondary" data-close-dialog>Cancel</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<form method="get" class="search" role="search">
  <input type="hidden" name="page" value="subjects">
  <label class="sr-only" for="subject-search">Search subjects</label>
  <input type="search" id="subject-search" name="q" value="<?=e($subjectSearch)?>" placeholder="Search code or title">
  <button>Search</button>
  <?php if ($subjectSearch !== ''): ?><a href="?page=subjects">Clear search</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th scope="col">Code</th>
        <th scope="col">Title</th>
        <th scope="col">Assignments</th>
        <th scope="col"><span class="sr-only">Actions</span></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($subjectRows as $row): ?>
      <tr class="row-link" data-href="?page=subjects&amp;edit=<?=(int)$row['id']?>" tabindex="0" aria-label="Edit <?=e($row['code'])?>">
        <td><strong><?=e($row['code'])?></strong></td>
        <td><?=e($row['title'])?></td>
        <td><?=(int)$row['assigned_count']?></td>
        <td><a href="?page=subjects&amp;edit=<?=(int)$row['id']?>">Edit</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$subjectRows): ?>
      <tr>
        <td colspan="4" class="empty-state">
          <strong><?=$subjectSearch !== '' ? 'No matching subjects' : 'No subjects yet'?></strong>
          <p><?=$subjectSearch !== '' ? 'Try another code or title.' : 'Add subject codes so registrars can assign teachers to blocks.'?></p>
          <?php if ($subjectSearch !== ''): ?><a href="?page=subjects">Clear search</a><?php else: ?><a href="?page=subjects&amp;add=1">Add subject</a><?php endif; ?>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
