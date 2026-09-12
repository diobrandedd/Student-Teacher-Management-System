<?php
declare(strict_types=1);
global $page, $enrollErrors, $enrollFieldErrors, $enrollInput, $enrollSubmitted, $courseOptions;
global $applications, $enrollmentStatusFilter, $selectedEnrollment, $selectedDocuments, $reviewErrors, $enrollmentId;
global $enrollmentFilters, $enrollmentTotal, $enrollmentPages, $enrollmentPage, $enrollmentStatusCounts, $enrollmentAcademicYears;

$agePreview = enrollment_age_from_dob((string)($enrollInput['date_of_birth'] ?? ''));
$enrollFieldErrors = is_array($enrollFieldErrors ?? null) ? $enrollFieldErrors : [];
$mobileLocal = enrollment_mobile_digits((string)($enrollInput['mobile'] ?? ''));
$fieldClass = static function (string $name) use ($enrollFieldErrors): string {
    return isset($enrollFieldErrors[$name]) ? ' is-invalid' : '';
};
$fieldMsg = static function (string $name) use ($enrollFieldErrors): string {
    if (!isset($enrollFieldErrors[$name])) return '';
    return '<p class="field-error" role="alert">' . e($enrollFieldErrors[$name]) . '</p>';
};
?>
<?php if ($page === 'enroll'): ?>
<div class="bar">
  <div>
    <h1>Enroll Now</h1>
    <p class="muted">Submit your application for college admission. The registrar reviews it before you can sign in.</p>
  </div>
  <a class="button secondary" href="?page=login">Back to sign in</a>
</div>
<?php if ($enrollSubmitted): ?>
<section class="card" role="status">
  <h2>Application received</h2>
  <p>The registrar will review your documents and details. Watch for notice from the college or university office. Sign-in credentials are created only after approval.</p>
  <p class="actions"><a class="button" href="?page=login">Return to sign in</a></p>
</section>
<?php else: ?>
<?php errors($enrollErrors); ?>
<?php if (!$courseOptions): ?>
<div class="notice error" role="alert">Enrollment is temporarily unavailable until an administrator adds at least one course.</div>
<?php else: ?>
<form class="card enroll-form" method="post" enctype="multipart/form-data" data-enroll-form novalidate>
  <?=csrf_field()?>
  <nav class="enroll-jump" aria-label="Form sections">
    <a href="#enroll-sec-identity">Identity</a>
    <a href="#enroll-sec-contact">Contact</a>
    <a href="#enroll-sec-family">Family</a>
    <a href="#enroll-sec-education">Education</a>
    <a href="#enroll-sec-program">Program</a>
    <a href="#enroll-sec-documents">Documents</a>
    <a href="#enroll-sec-consent">Consent</a>
  </nav>
  <fieldset class="form-section" id="enroll-sec-identity">
    <legend>Identity</legend>
    <p class="muted">Legal name as on your PSA birth certificate.</p>
    <div class="grid">
      <div><label for="enroll-last">Last name</label><input id="enroll-last" name="last_name" required maxlength="80" value="<?=e($enrollInput['last_name'])?>" autocomplete="family-name" data-enroll-name class="<?=trim($fieldClass('last_name'))?>" aria-invalid="<?=isset($enrollFieldErrors['last_name'])?'true':'false'?>"><?=$fieldMsg('last_name')?></div>
      <div><label for="enroll-first">First name</label><input id="enroll-first" name="first_name" required maxlength="80" value="<?=e($enrollInput['first_name'])?>" autocomplete="given-name" data-enroll-name class="<?=trim($fieldClass('first_name'))?>" aria-invalid="<?=isset($enrollFieldErrors['first_name'])?'true':'false'?>"><?=$fieldMsg('first_name')?></div>
      <div>
        <label for="enroll-middle">Middle name</label>
        <input id="enroll-middle" name="middle_name" maxlength="80" value="<?=e($enrollInput['middle_name'])?>" autocomplete="additional-name" data-enroll-name class="<?=trim($fieldClass('middle_name'))?>" aria-invalid="<?=isset($enrollFieldErrors['middle_name'])?'true':'false'?>" <?=$enrollInput['no_middle_name']?'disabled':''?>>
        <label class="inline-check"><input type="checkbox" name="no_middle_name" value="1" data-no-middle-name <?=$enrollInput['no_middle_name']?'checked':''?>> No middle name</label>
        <?=$fieldMsg('middle_name')?>
      </div>
      <div><label for="enroll-suffix">Suffix <span class="muted">(optional)</span></label><input id="enroll-suffix" name="suffix" maxlength="20" value="<?=e($enrollInput['suffix'])?>" placeholder="Jr., III" class="<?=trim($fieldClass('suffix'))?>"></div>
      <div><label for="enroll-sex">Sex</label><select id="enroll-sex" name="sex" required class="<?=trim($fieldClass('sex'))?>"><option value="">Select</option><?php foreach(enrollment_sex_options() as $k=>$v):?><option value="<?=e($k)?>" <?=$enrollInput['sex']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select><?=$fieldMsg('sex')?></div>
      <div><label for="enroll-civil">Civil status</label><select id="enroll-civil" name="civil_status" required class="<?=trim($fieldClass('civil_status'))?>"><option value="">Select</option><?php foreach(enrollment_civil_status_options() as $k=>$v):?><option value="<?=e($k)?>" <?=$enrollInput['civil_status']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select><?=$fieldMsg('civil_status')?></div>
      <div><label for="enroll-citizenship">Citizenship</label><input id="enroll-citizenship" name="citizenship" required maxlength="80" value="<?=e($enrollInput['citizenship'])?>" class="<?=trim($fieldClass('citizenship'))?>"><?=$fieldMsg('citizenship')?></div>
      <div><label for="enroll-religion">Religion</label><input id="enroll-religion" name="religion" required maxlength="80" value="<?=e($enrollInput['religion'])?>" class="<?=trim($fieldClass('religion'))?>"><?=$fieldMsg('religion')?></div>
      <div><label for="enroll-dob">Date of birth</label><input type="date" id="enroll-dob" name="date_of_birth" required value="<?=e($enrollInput['date_of_birth'])?>" data-enroll-dob class="<?=trim($fieldClass('date_of_birth'))?>"><p class="muted" data-enroll-age><?=$agePreview!==null?'Age: '.$agePreview:'Age is calculated from date of birth.'?></p><?=$fieldMsg('date_of_birth')?></div>
      <div><label for="enroll-pob">Place of birth</label><input id="enroll-pob" name="place_of_birth" required maxlength="190" value="<?=e($enrollInput['place_of_birth'])?>" class="<?=trim($fieldClass('place_of_birth'))?>"><?=$fieldMsg('place_of_birth')?></div>
    </div>
  </fieldset>

  <fieldset class="form-section" id="enroll-sec-contact">
    <legend>Contact</legend>
    <div class="grid">
      <div><label for="enroll-email">Email</label><input type="email" id="enroll-email" name="email" required maxlength="190" value="<?=e($enrollInput['email'])?>" class="<?=trim($fieldClass('email'))?>"><?=$fieldMsg('email')?></div>
      <div>
        <label for="enroll-mobile">Mobile number</label>
        <div class="mobile-input-row<?=isset($enrollFieldErrors['mobile'])?' is-invalid-wrap':''?>">
          <span class="mobile-prefix" aria-hidden="true">63+</span>
          <input id="enroll-mobile" name="mobile" required maxlength="10" inputmode="numeric" pattern="\d{10}" autocomplete="tel-national" value="<?=e($mobileLocal)?>" placeholder="9631014585" data-enroll-mobile class="<?=trim($fieldClass('mobile'))?>" aria-describedby="enroll-mobile-help" aria-invalid="<?=isset($enrollFieldErrors['mobile'])?'true':'false'?>">
        </div>
        <p class="muted" id="enroll-mobile-help">Country code 63+ is fixed. Enter the next 10 digits only (numbers, no spaces or letters).</p>
        <?=$fieldMsg('mobile')?>
      </div>
    </div>
    <p class="muted">Current address — select province (all Philippine provinces plus Metro Manila), then city/municipality, then barangay.</p>
    <div class="grid" data-ph-locations data-locations-url="data/ph-locations.json"
         data-province="<?=e($enrollInput['province_code'])?>"
         data-city="<?=e($enrollInput['city_code'])?>"
         data-barangay="<?=e($enrollInput['barangay_code'])?>">
      <div>
        <label for="enroll-province">Province</label>
        <select id="enroll-province" name="province_code" required data-ph-province></select>
        <input type="hidden" name="province_name" value="<?=e($enrollInput['province_name'])?>" data-ph-province-name>
      </div>
      <div>
        <label for="enroll-city">City / Municipality</label>
        <select id="enroll-city" name="city_code" required data-ph-city disabled></select>
        <input type="hidden" name="city_name" value="<?=e($enrollInput['city_name'])?>" data-ph-city-name>
      </div>
      <div>
        <label for="enroll-barangay">Barangay</label>
        <select id="enroll-barangay" name="barangay_code" required data-ph-barangay disabled></select>
        <input type="hidden" name="barangay_name" value="<?=e($enrollInput['barangay_name'])?>" data-ph-barangay-name>
      </div>
      <div><label for="enroll-line1">Address line 1</label><input id="enroll-line1" name="address_line1" required maxlength="190" value="<?=e($enrollInput['address_line1'])?>" placeholder="House no., street"></div>
      <div><label for="enroll-line2">Address line 2 <span class="muted">(optional)</span></label><input id="enroll-line2" name="address_line2" maxlength="190" value="<?=e($enrollInput['address_line2'])?>" placeholder="Subdivision, landmark"></div>
    </div>
  </fieldset>

  <fieldset class="form-section" id="enroll-sec-family">
    <legend>Family and guardian</legend>
    <div class="grid">
      <div><label for="enroll-mother">Mother’s maiden name</label><input id="enroll-mother" name="mother_maiden_name" required maxlength="150" value="<?=e($enrollInput['mother_maiden_name'])?>"></div>
      <div><label for="enroll-father">Father’s name</label><input id="enroll-father" name="father_name" required maxlength="150" value="<?=e($enrollInput['father_name'])?>"></div>
      <div><label for="enroll-guardian">Guardian name</label><input id="enroll-guardian" name="guardian_name" required maxlength="150" value="<?=e($enrollInput['guardian_name'])?>"></div>
      <div><label for="enroll-grel">Guardian relationship</label><select id="enroll-grel" name="guardian_relationship" required><option value="">Select</option><?php foreach(enrollment_guardian_relationships() as $k=>$v):?><option value="<?=e($k)?>" <?=$enrollInput['guardian_relationship']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
      <div><label for="enroll-gnum">Guardian number</label><input id="enroll-gnum" name="guardian_number" required maxlength="20" value="<?=e($enrollInput['guardian_number'])?>"></div>
      <div><label for="enroll-gaddr">Guardian address <span class="muted">(optional if same as yours)</span></label><input id="enroll-gaddr" name="guardian_address" maxlength="255" value="<?=e($enrollInput['guardian_address'])?>"></div>
      <div><label for="enroll-ename">Emergency contact name</label><input id="enroll-ename" name="emergency_name" required maxlength="150" value="<?=e($enrollInput['emergency_name'])?>" class="<?=trim($fieldClass('emergency_name'))?>"><?=$fieldMsg('emergency_name')?></div>
      <div><label for="enroll-erel">Emergency relationship</label><input id="enroll-erel" name="emergency_relationship" required maxlength="80" value="<?=e($enrollInput['emergency_relationship'])?>" class="<?=trim($fieldClass('emergency_relationship'))?>"><?=$fieldMsg('emergency_relationship')?></div>
      <div><label for="enroll-enum">Emergency number</label><input id="enroll-enum" name="emergency_number" required maxlength="20" value="<?=e($enrollInput['emergency_number'])?>" class="<?=trim($fieldClass('emergency_number'))?>"><?=$fieldMsg('emergency_number')?></div>
    </div>
  </fieldset>

  <fieldset class="form-section" id="enroll-sec-education">
    <legend>Education background</legend>
    <p class="muted">Choose <strong>New student</strong> if you are entering from another school, or <strong>Moving up</strong> if you already study here and are advancing a year.</p>
    <div class="grid">
      <div><label for="enroll-apptype">Application type</label><select id="enroll-apptype" name="application_type" required data-enroll-app-type><?php foreach(enrollment_application_types() as $k=>$v):?><option value="<?=e($k)?>" <?=$enrollInput['application_type']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
      <div data-enroll-student-id hidden>
        <label for="enroll-student-id">Student ID</label>
        <input id="enroll-student-id" name="student_number" maxlength="30" value="<?=e($enrollInput['student_number'])?>" pattern="\d{3,30}" inputmode="numeric" spellcheck="false" data-enroll-student-id-input placeholder="e.g. 20260001" class="<?=trim($fieldClass('student_number'))?>" aria-invalid="<?=isset($enrollFieldErrors['student_number'])?'true':'false'?>">
        <p class="muted">Enter the exact student ID on file (numbers only). If your ID is printed as 2026-0001, type 20260001.</p>
        <?=$fieldMsg('student_number')?>
      </div>
      <div data-enroll-new-id-note>
        <p class="muted" style="margin-top:18px">New students receive a numeric student ID automatically on approval, starting with <strong><?=e((string)date('Y'))?>0001</strong>, <strong><?=e((string)date('Y'))?>0002</strong>, and so on.</p>
      </div>
      <div data-enroll-prior-school><label for="enroll-school">Last school or institution attended</label><input id="enroll-school" name="last_school" maxlength="190" value="<?=e($enrollInput['last_school']==='This institution (moving up)'?'':$enrollInput['last_school'])?>" <?=$enrollInput['application_type']!=='moving_up'?'required':''?> class="<?=trim($fieldClass('last_school'))?>"><?=$fieldMsg('last_school')?></div>
      <div data-enroll-prior-school><label for="enroll-grad">Year graduated / last attended</label><input id="enroll-grad" name="year_graduated" maxlength="20" value="<?=e($enrollInput['year_graduated'])?>" placeholder="2025" <?=$enrollInput['application_type']!=='moving_up'?'required':''?> class="<?=trim($fieldClass('year_graduated'))?>"><?=$fieldMsg('year_graduated')?></div>
      <div data-enroll-prior-school><label for="enroll-strand">Previous program or SHS track <span class="muted">(optional)</span></label><input id="enroll-strand" name="strand_or_previous_course" maxlength="150" value="<?=e($enrollInput['strand_or_previous_course'])?>" placeholder="e.g. BSIT, STEM, ABM"></div>
    </div>
  </fieldset>

  <fieldset class="form-section" id="enroll-sec-program">
    <legend>Program intent</legend>
    <div class="grid">
      <div data-enroll-new-program><label for="enroll-course">Preferred college program</label><select id="enroll-course" name="course_id" required data-enroll-course-new><option value="">Select a program</option><?php foreach($courseOptions as $c):?><option value="<?=(int)$c['id']?>" <?=((string)$enrollInput['course_id']===(string)$c['id'] && ($enrollInput['application_type']??'')!=='moving_up')?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></div>
      <div data-enroll-new-program><label for="enroll-course2">Second choice <span class="muted">(optional)</span></label><select id="enroll-course2" name="course_id_second" data-enroll-course-second><option value="">None</option><?php foreach($courseOptions as $c):?><option value="<?=(int)$c['id']?>" <?=((string)$enrollInput['course_id_second']===(string)$c['id'])?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></div>
      <div data-enroll-current-program hidden><label for="enroll-course-current">College program</label><select id="enroll-course-current" name="course_id" required disabled data-enroll-course-current><option value="">Select your program</option><?php foreach($courseOptions as $c):?><option value="<?=(int)$c['id']?>" <?=((string)$enrollInput['course_id']===(string)$c['id'] && ($enrollInput['application_type']??'')==='moving_up')?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select><p class="muted">Select the program you are already enrolled in at this college or university.</p></div>
      <div>
        <label for="enroll-year">College year applying for</label>
        <select id="enroll-year" name="year_level" required data-enroll-year>
          <?php
          $yearChoices = college_year_labels();
          if (($enrollInput['application_type'] ?? 'new') !== 'moving_up') {
              $yearChoices = [1 => $yearChoices[1]];
              if ((int)($enrollInput['year_level'] ?? 1) !== 1) {
                  $enrollInput['year_level'] = '1';
              }
          } else {
              unset($yearChoices[1]);
              if (!isset($yearChoices[(int)($enrollInput['year_level'] ?? 0)])) {
                  $enrollInput['year_level'] = '2';
              }
          }
          foreach ($yearChoices as $y => $label):
          ?>
          <option value="<?=$y?>" <?=((string)$enrollInput['year_level']===(string)$y)?'selected':''?>><?=e($label)?></option>
          <?php endforeach; ?>
        </select>
        <p class="muted" data-enroll-year-hint<?=($enrollInput['application_type'] ?? '')==='moving_up'?' hidden':''?>>New students enroll as <strong>1st year</strong>. Use <strong>Moving up</strong> for 2nd–4th year.</p>
        <?=$fieldMsg('year_level')?>
      </div>
      <div><label for="enroll-ay">Academic year</label><input id="enroll-ay" name="academic_year" required maxlength="20" value="<?=e($enrollInput['academic_year'])?>" placeholder="2026-2027"></div>
      <div><label for="enroll-sem">Semester</label><select id="enroll-sem" name="semester" required><?php foreach(enrollment_semester_options() as $k=>$v):?><option value="<?=e((string)$k)?>" <?=((string)$enrollInput['semester']===(string)$k)?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
    </div>
  </fieldset>

  <fieldset class="form-section" id="enroll-sec-documents">
    <legend>Required documents</legend>
    <p class="muted">PDF, JPEG, or PNG up to 5 MB. ID photo must be JPEG or PNG.</p>
    <div class="grid">
      <div><label for="enroll-psa">PSA birth certificate</label><input type="file" id="enroll-psa" name="psa_birth" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"></div>
      <div><label for="enroll-idphoto">ID photo</label><input type="file" id="enroll-idphoto" name="id_photo" required accept=".jpg,.jpeg,.png,image/jpeg,image/png"></div>
    </div>
  </fieldset>

  <fieldset class="form-section" id="enroll-sec-consent">
    <legend>Consent</legend>
    <label class="inline-check"><input type="checkbox" name="privacy_consent" value="1" required <?=$enrollInput['privacy_consent']?'checked':''?>> I agree that this college or university may collect and process my personal data for enrollment and academic records.</label>
  </fieldset>

  <div class="actions"><button>Submit application</button><a class="button secondary" href="?page=login">Cancel</a></div>
</form>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($page === 'enrollments'): ?>
<?php
$filterQs = static function (array $overrides = []) use ($enrollmentFilters): string {
    return enrollment_list_query_string($enrollmentFilters, $overrides);
};
$hasExtraFilters = $enrollmentFilters['q'] !== ''
    || $enrollmentFilters['course_id']
    || $enrollmentFilters['year_level']
    || $enrollmentFilters['application_type']
    || $enrollmentFilters['academic_year'] !== ''
    || $enrollmentFilters['semester'];
?>
<div class="bar">
  <div>
    <h1>Enrollments</h1>
    <p class="muted">Review applications, then approve to create sign-in or reject with a reason.</p>
  </div>
</div>
<section class="queue-tools" aria-label="Enrollment filters">
  <nav class="status-tabs" aria-label="Enrollment status">
    <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $key=>$label): ?>
    <a href="?<?=e($filterQs(['status'=>$key,'page'=>1,'id'=>null]))?>" <?=$enrollmentStatusFilter===$key?'aria-current="page"':''?>><?=e($label)?> <span class="tab-count"><?=(int)($enrollmentStatusCounts[$key] ?? 0)?></span></a>
    <?php endforeach; ?>
  </nav>
  <form class="filter-panel filter-panel--queue" method="get" role="search">
    <input type="hidden" name="page" value="enrollments">
    <input type="hidden" name="status" value="<?=e($enrollmentStatusFilter)?>">
    <div class="filter-search">
      <label for="enroll-search">Search</label>
      <input id="enroll-search" type="search" name="q" value="<?=e($enrollmentFilters['q'])?>" placeholder="Name, email, student ID, or mobile">
    </div>
    <div class="filter-actions">
      <button>Apply</button>
      <?php if ($hasExtraFilters): ?><a class="button secondary" href="?<?=e($filterQs(['q'=>'','course_id'=>null,'year_level'=>null,'application_type'=>null,'academic_year'=>'','semester'=>null,'page'=>1,'id'=>null]))?>">Clear</a><?php endif; ?>
    </div>
    <div class="filter-dims" role="group" aria-label="Queue dimensions">
      <div>
        <label for="enroll-filter-course">Program</label>
        <select id="enroll-filter-course" name="course_id" data-filter-autosubmit>
          <option value="">All programs</option>
          <?php foreach ($courseOptions as $c): ?>
          <option value="<?=(int)$c['id']?>" <?=((int)($enrollmentFilters['course_id'] ?? 0)===(int)$c['id'])?'selected':''?>><?=e($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="enroll-filter-year">Year</label>
        <select id="enroll-filter-year" name="year_level" data-filter-autosubmit>
          <option value="">All years</option>
          <?php foreach (college_year_labels() as $y=>$label): ?>
          <option value="<?=(int)$y?>" <?=((int)($enrollmentFilters['year_level'] ?? 0)===(int)$y)?'selected':''?>><?=e($label)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="enroll-filter-type">Type</label>
        <select id="enroll-filter-type" name="application_type" data-filter-autosubmit>
          <option value="">All types</option>
          <?php foreach (enrollment_application_types() as $k=>$v): ?>
          <option value="<?=e($k)?>" <?=($enrollmentFilters['application_type']??'')===$k?'selected':''?>><?=e($v)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="enroll-filter-ay">Academic year</label>
        <input id="enroll-filter-ay" list="enroll-ay-list" name="academic_year" value="<?=e($enrollmentFilters['academic_year'])?>" maxlength="20" placeholder="2026-2027" autocomplete="off">
        <datalist id="enroll-ay-list">
          <?php foreach ($enrollmentAcademicYears as $ay): ?>
          <option value="<?=e($ay)?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label for="enroll-filter-sem">Semester</label>
        <select id="enroll-filter-sem" name="semester" data-filter-autosubmit>
          <option value="">All</option>
          <?php foreach (enrollment_semester_options() as $k=>$v): ?>
          <option value="<?=e((string)$k)?>" <?=($enrollmentFilters['semester']??'')===(string)$k?'selected':''?>><?=e($v)?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </form>
  <?php
  $showStatusCol = $enrollmentStatusFilter === 'all';
  $metaBits = [$enrollmentTotal . ' ' . ($enrollmentTotal === 1 ? 'application' : 'applications')];
  if ($hasExtraFilters) {
      $metaBits[] = 'filtered';
  }
  $metaBits[] = 'Page ' . $enrollmentPage . ' of ' . $enrollmentPages;
  ?>
  <p class="queue-meta"><?=e(implode(' · ', $metaBits))?> · Click a row to review</p>
</section>
<div class="table-wrap queue-table<?=$showStatusCol?'':' queue-table--compact-status'?>">
  <table>
    <thead><tr>
      <th scope="col">Applicant</th>
      <th scope="col">Program</th>
      <th scope="col">Term</th>
      <th scope="col">Type</th>
      <?php if ($showStatusCol): ?><th scope="col">Status</th><?php endif; ?>
      <th scope="col">Submitted</th>
    </tr></thead>
    <tbody>
    <?php if (empty($applications)): ?>
      <tr><td colspan="<?=$showStatusCol?6:5?>" class="empty-state"><strong>No applications</strong><p><?=$hasExtraFilters?'Try clearing or widening filters.':($enrollmentStatusFilter==='pending'?'Nothing pending — new Enroll Now submissions appear here.':'No applications in this status.')?></p><?php if($hasExtraFilters):?><a href="?<?=e($filterQs(['q'=>'','course_id'=>null,'year_level'=>null,'application_type'=>null,'academic_year'=>'','semester'=>null,'page'=>1,'id'=>null]))?>">Clear filters</a><?php endif;?></td></tr>
    <?php else: foreach ($applications as $row):
      $submittedAt = (string)($row['created_at'] ?? '');
      $submittedDay = preg_match('/^\d{4}-\d{2}-\d{2}/', $submittedAt, $m) ? $m[0] : $submittedAt;
      $submittedTime = preg_match('/\d{2}:\d{2}/', $submittedAt, $t) ? $t[0] : '';
      $isMovingUpRow = ($row['application_type'] ?? '') === 'moving_up';
      $fromYear = isset($row['current_year_level']) && $row['current_year_level'] !== null && $row['current_year_level'] !== ''
          ? (int)$row['current_year_level'] : null;
      $toYear = (int)$row['year_level'];
      $yearLine = $isMovingUpRow
          ? enrollment_move_year_label($fromYear, $toYear)
          : college_year_label($toYear);
    ?>
      <tr class="row-link" data-href="?<?=e($filterQs(['id'=>(int)$row['id']]))?>" tabindex="0" aria-label="Open application for <?=e($row['last_name'].', '.$row['first_name'])?>">
        <td><strong><?=e($row['last_name'].', '.$row['first_name'])?></strong><br><span class="muted"><?=e($row['email'])?><?php if (!empty($row['student_number'])): ?> · <?=e($row['student_number'])?><?php endif; ?></span></td>
        <td><?=e($row['course_name'])?><br><span class="muted"><?=e($yearLine)?></span></td>
        <td><span class="num"><?=e($row['academic_year'])?></span><br><span class="muted"><?=e(enrollment_semester_options()[$row['semester']] ?? $row['semester'])?></span></td>
        <td><?=e(enrollment_application_types()[$row['application_type']] ?? $row['application_type'])?></td>
        <?php if ($showStatusCol): ?><td><span class="badge badge-enroll-<?=e($row['status'])?>"><?=e(ucfirst($row['status']))?></span></td><?php endif; ?>
        <td><time datetime="<?=e($submittedAt)?>" title="<?=e($submittedAt)?>"><?=e($submittedDay)?><?php if ($submittedTime !== ''): ?><br><span class="muted"><?=e($submittedTime)?></span><?php endif; ?></time></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php if ($enrollmentPages > 1): ?>
<nav class="pager" aria-label="Enrollment pages">
  <span>Page <?=$enrollmentPage?> of <?=$enrollmentPages?></span>
  <?php if ($enrollmentPage > 1): ?><a href="?<?=e($filterQs(['page'=>$enrollmentPage-1,'id'=>null]))?>">Previous</a><?php endif; ?>
  <?php if ($enrollmentPage < $enrollmentPages): ?><a href="?<?=e($filterQs(['page'=>$enrollmentPage+1,'id'=>null]))?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>

<?php if ($selectedEnrollment): ?>
<dialog open class="wide-dialog" aria-labelledby="enroll-review-title" data-return-url="?<?=e($filterQs(['id'=>null]))?>">
  <div class="bar">
    <div>
      <h2 id="enroll-review-title" tabindex="-1"><?=e($selectedEnrollment['last_name'].', '.$selectedEnrollment['first_name'])?></h2>
      <p class="muted"><span class="badge badge-enroll-<?=e($selectedEnrollment['status'])?>"><?=e(ucfirst($selectedEnrollment['status']))?></span> · Submitted <?=e($selectedEnrollment['created_at'])?></p>
    </div>
    <button type="button" class="icon-button secondary" data-close-dialog aria-label="Close">×</button>
  </div>
  <?php errors($reviewErrors); ?>
  <section class="form-section">
    <h3>Program</h3>
    <?php
    $modalIsMovingUp = ($selectedEnrollment['application_type'] ?? '') === 'moving_up';
    $modalFromYear = isset($selectedEnrollment['current_year_level']) && $selectedEnrollment['current_year_level'] !== null && $selectedEnrollment['current_year_level'] !== ''
        ? (int)$selectedEnrollment['current_year_level'] : null;
    $modalToYear = (int)$selectedEnrollment['year_level'];
    ?>
    <p><strong><?=e($selectedEnrollment['course_name'])?></strong> · <?=e($modalIsMovingUp ? enrollment_move_year_label($modalFromYear, $modalToYear) : college_year_label($modalToYear))?> · <?=e($selectedEnrollment['academic_year'])?> · <?=e(enrollment_semester_options()[$selectedEnrollment['semester']] ?? $selectedEnrollment['semester'])?></p>
    <?php if ($modalIsMovingUp): ?>
    <p><strong>Moving up</strong><?php if ($modalFromYear): ?> from <strong><?=e(college_year_label($modalFromYear))?></strong><?php endif; ?> to <strong><?=e(college_year_label($modalToYear))?></strong>.</p>
    <?php endif; ?>
    <?php if (!empty($selectedEnrollment['student_number'])): ?><p>Student ID: <strong><?=e($selectedEnrollment['student_number'])?></strong></p><?php elseif (($selectedEnrollment['application_type'] ?? '') === 'new'): ?><p class="muted">Student ID will be assigned on approval (<?=e((string)date('Y'))?>0001 format).</p><?php endif; ?>
    <?php if (($selectedEnrollment['application_type'] ?? '') !== 'moving_up' && $selectedEnrollment['course_name_second']): ?><p class="muted">Second choice: <?=e($selectedEnrollment['course_name_second'])?></p><?php endif; ?>
  </section>
  <section class="form-section">
    <h3>Identity and contact</h3>
    <p><?=e(trim($selectedEnrollment['first_name'].' '.($selectedEnrollment['middle_name'] ? $selectedEnrollment['middle_name'].' ' : '').$selectedEnrollment['last_name'].($selectedEnrollment['suffix'] ? ' '.$selectedEnrollment['suffix'] : '')))?></p>
    <p class="muted"><?=e(enrollment_sex_options()[$selectedEnrollment['sex']] ?? '')?> · <?=e(enrollment_civil_status_options()[$selectedEnrollment['civil_status']] ?? '')?> · Age <?=e((string)(enrollment_age_from_dob($selectedEnrollment['date_of_birth']) ?? '—'))?></p>
    <p><?=e($selectedEnrollment['email'])?> · <?=e($selectedEnrollment['mobile'])?></p>
    <p class="muted"><?=e(enrollment_compose_address($selectedEnrollment))?></p>
  </section>
  <section class="form-section">
    <h3>Family</h3>
    <p>Mother’s maiden name: <?=e($selectedEnrollment['mother_maiden_name'])?></p>
    <p>Father’s name: <?=e($selectedEnrollment['father_name'])?></p>
    <p>Guardian: <?=e($selectedEnrollment['guardian_name'])?> (<?=e(enrollment_guardian_relationships()[$selectedEnrollment['guardian_relationship']] ?? '')?>) · <?=e($selectedEnrollment['guardian_number'])?></p>
  </section>
  <section class="form-section">
    <h3>Education</h3>
    <?php if ($modalIsMovingUp): ?>
    <p>Moving up within this institution.</p>
    <?php else: ?>
    <p><?=e(enrollment_application_types()[$selectedEnrollment['application_type']] ?? '')?><?php if (trim((string)($selectedEnrollment['last_school'] ?? '')) !== '' && $selectedEnrollment['last_school'] !== 'This institution (moving up)'): ?> · <?=e($selectedEnrollment['last_school'])?><?php endif; ?></p>
    <?php endif; ?>
  </section>
  <section class="form-section">
    <h3>Documents</h3>
    <ul class="weight-list">
      <?php foreach ($selectedDocuments as $doc): ?>
      <li>
        <a href="?page=enrollment_document&amp;id=<?=(int)$doc['id']?>" target="_blank" rel="noopener"><?=e(enrollment_doc_types()[$doc['doc_type']] ?? $doc['doc_type'])?></a>
        <span class="muted"> · <?=e($doc['original_name'])?></span>
        <?php if (str_starts_with($doc['mime_type'], 'image/')): ?>
        <div class="enroll-doc-preview"><img src="?page=enrollment_document&amp;id=<?=(int)$doc['id']?>" alt="<?=e(enrollment_doc_types()[$doc['doc_type']] ?? 'Document')?>"></div>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php if ($selectedEnrollment['status'] === 'pending'): ?>
  <p class="muted">Approve creates or updates a student record. New students get a one-time temporary password shown after approval — share credentials privately.</p>
  <form method="post" action="?<?=e($filterQs(['id'=>(int)$selectedEnrollment['id']]))?>" data-enroll-review>
    <?=csrf_field()?>
    <div class="form-section">
      <label for="reject-reason">Reject reason <span class="muted">(required only when rejecting)</span></label>
      <textarea id="reject-reason" name="reject_reason" maxlength="500" rows="3"></textarea>
    </div>
    <div class="actions">
      <button name="review_action" value="approve" data-confirm-approve="Approve this enrollee? This creates or updates their student record and issues a one-time temporary password when needed.">Approve enrollee</button>
      <button type="submit" class="danger" name="review_action" value="reject">Reject</button>
      <button type="button" class="secondary" data-close-dialog>Close</button>
    </div>
  </form>
  <?php else: ?>
  <p class="muted">Reviewed by <?=e($selectedEnrollment['reviewer_name'] ?? '—')?> on <?=e($selectedEnrollment['reviewed_at'] ?? '—')?><?php if ($selectedEnrollment['reject_reason']): ?> · <?=e($selectedEnrollment['reject_reason'])?><?php endif; ?></p>
  <div class="actions"><button type="button" class="secondary" data-close-dialog>Close</button></div>
  <?php endif; ?>
</dialog>
<?php endif; ?>
<?php endif; ?>
