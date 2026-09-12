<?php
declare(strict_types=1);
?>
<div class="bar">
  <div>
    <h1>Blocking</h1>
    <p class="muted">Active students with no block yet. Open a row to place them into a block.</p>
  </div>
</div>

<section class="queue-tools" aria-label="Blocking filters">
  <form method="get" class="filter-panel filter-panel--queue" role="search">
    <input type="hidden" name="page" value="blocking">
    <div class="filter-search">
      <label for="blocking-q">Search</label>
      <input id="blocking-q" type="search" name="q" value="<?=e($blockingFilters['q'])?>" placeholder="Name, email, student ID, mobile, or username">
    </div>
    <div class="filter-actions">
      <button type="submit">Apply</button>
      <?php if ($hasExtraFilters): ?>
      <a class="button secondary" href="?<?=e($filterQs(['q'=>'','course_id'=>null,'year_level'=>null,'page'=>1,'id'=>null]))?>">Clear</a>
      <?php endif; ?>
    </div>
    <div class="filter-dims" role="group" aria-label="Queue dimensions">
      <div>
        <label for="blocking-course">Program</label>
        <select id="blocking-course" name="course_id" data-filter-autosubmit>
          <option value="">All programs</option>
          <?php foreach ($courseOptions as $c): ?>
          <option value="<?=(int)$c['id']?>" <?=((int)($blockingFilters['course_id'] ?? 0)===(int)$c['id'])?'selected':''?>><?=e($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="blocking-year">Year</label>
        <select id="blocking-year" name="year_level" data-filter-autosubmit>
          <option value="">All years</option>
          <?php foreach (college_year_labels() as $y => $label): ?>
          <option value="<?=(int)$y?>" <?=((int)($blockingFilters['year_level'] ?? 0)===(int)$y)?'selected':''?>><?=e($label)?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </form>
  <?php
  $metaBits = [$blockingTotal . ' ' . ($blockingTotal === 1 ? 'student' : 'students') . ' need a block'];
  if ($hasExtraFilters) {
      $metaBits[] = 'filtered';
  }
  $metaBits[] = 'Page ' . $blockingPage . ' of ' . $blockingPages;
  ?>
  <p class="queue-meta"><?=e(implode(' · ', $metaBits))?> · Click a row to assign</p>
</section>

<div class="table-wrap queue-table">
  <table>
    <thead>
      <tr>
        <th scope="col">Student</th>
        <th scope="col">Program</th>
        <th scope="col">Source</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$blockingRows): ?>
      <tr>
        <td colspan="3" class="empty-state">
          <strong>No students waiting for a block</strong>
          <p><?=$hasExtraFilters ? 'Try clearing or widening filters.' : 'Approved enrollees and active students without a block appear here.'?></p>
          <?php if ($hasExtraFilters): ?><a href="?<?=e($filterQs(['q'=>'','course_id'=>null,'year_level'=>null,'page'=>1,'id'=>null]))?>">Clear filters</a><?php endif; ?>
        </td>
      </tr>
      <?php else: foreach ($blockingRows as $row): ?>
      <tr class="row-link" data-href="?<?=e($filterQs(['id'=>(int)$row['id']]))?>" tabindex="0" aria-label="Assign block for <?=e($row['last_name'].', '.$row['first_name'])?>">
        <td>
          <strong><?=e($row['last_name'].', '.$row['first_name'])?></strong><br>
          <span class="muted"><?=e($row['student_number'])?><?php if (!empty($row['email'])): ?> · <?=e($row['email'])?><?php endif; ?></span>
        </td>
        <td><?=e($row['course_name'] ?: ($row['course'] ?? '—'))?><br><span class="muted"><?=e(college_year_label($row['year_level']))?></span></td>
        <td>
          <?php if (!empty($row['enrollment_approved_at'])): ?>
          <span class="badge badge-enroll-approved">Approved enrollee</span>
          <?php else: ?>
          <span class="muted">Student record</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php if ($blockingPages > 1): ?>
<nav class="pager" aria-label="Blocking pages">
  <span>Page <?=$blockingPage?> of <?=$blockingPages?></span>
  <?php if ($blockingPage > 1): ?><a href="?<?=e($filterQs(['page'=>$blockingPage-1,'id'=>null]))?>">Previous</a><?php endif; ?>
  <?php if ($blockingPage < $blockingPages): ?><a href="?<?=e($filterQs(['page'=>$blockingPage+1,'id'=>null]))?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>

<?php if ($selectedStudent): ?>
<dialog open class="wide-dialog" aria-labelledby="blocking-assign-title" data-return-url="?<?=e($filterQs(['id'=>null]))?>">
  <div class="bar">
    <div>
      <h2 id="blocking-assign-title" tabindex="-1"><?=e($selectedStudent['last_name'].', '.$selectedStudent['first_name'])?></h2>
      <p class="muted"><?=e($selectedStudent['student_number'])?> · <?=e($selectedStudent['course_name'] ?: ($selectedStudent['course'] ?? '—'))?> · <?=e(college_year_label($selectedStudent['year_level']))?></p>
    </div>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button>
  </div>
  <?php errors($blockingErrors); ?>
  <section class="form-section">
    <h3>Student</h3>
    <p><?=e(trim($selectedStudent['first_name'].' '.(($selectedStudent['middle_name'] ?? '') !== '' ? $selectedStudent['middle_name'].' ' : '').$selectedStudent['last_name']))?></p>
    <p class="muted"><?=e((string)($selectedStudent['email'] ?? ''))?><?php if (!empty($selectedStudent['phone'])): ?> · <?=e((string)$selectedStudent['phone'])?><?php endif; ?></p>
    <?php if (!empty($selectedStudent['enrollment_approved_at'])): ?>
    <p>Approved enrollee<?php if (!empty($selectedStudent['approver_name'])): ?> by <strong><?=e((string)$selectedStudent['approver_name'])?></strong><?php endif; ?> · <?=e((string)$selectedStudent['enrollment_approved_at'])?></p>
    <?php endif; ?>
  </section>
  <form method="post" action="?<?=e($filterQs(['id'=>(int)$selectedStudent['id']]))?>">
    <?=csrf_field()?>
    <input type="hidden" name="student_id" value="<?=(int)$selectedStudent['id']?>">
    <div class="form-section">
      <label for="blocking-block">Assign to block</label>
      <?php if (!$blockOptions): ?>
      <div class="notice">No <?=e(college_year_label($selectedStudent['year_level'] ?? null))?> blocks yet.<?php if ((user()['role'] ?? '') === 'admin'): ?> <a href="?page=blocks&amp;add=1">Create a block</a> for this college year first.<?php else: ?> Ask an administrator to create a <?=e(college_year_label($selectedStudent['year_level'] ?? null))?> block on <strong>Blocks</strong>.<?php endif; ?></div>
      <?php else: ?>
      <select id="blocking-block" name="block_id" required>
        <option value="">Select a block</option>
        <?php foreach ($blockOptions as $b): ?>
        <option value="<?=(int)$b['id']?>"><?=e(block_label($b))?></option>
        <?php endforeach; ?>
      </select>
      <p class="muted">Only <?=e(college_year_label($selectedStudent['year_level'] ?? null))?> blocks are listed. Students join the teacher’s subjects for this block automatically.</p>
      <?php endif; ?>
    </div>
    <div class="actions">
      <button <?=$blockOptions ? '' : 'disabled'?>>Assign to block</button>
      <button type="button" class="secondary" data-close-dialog>Cancel</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
