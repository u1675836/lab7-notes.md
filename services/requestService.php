<?php
require_once dirname(__DIR__).'/includes/validation.php';
require_once dirname(__DIR__).'/repositories/studentPDO.php';
require_once dirname(__DIR__).'/repositories/requestPDO.php';
function escapeHtml($v) {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
// Week 4 form fields map to the Week 6 schema without adding database columns.
// topic contains JSON {reason,topics}; preferred_time stores Morning/Afternoon/Evening.
function decodeTopic($topic) {
    $v=json_decode($topic,true);
    return is_array($v)&&isset($v['reason'])&&is_array($v['topics']??null) ? $v : ['reason'=>$topic,'topics'=>[]];
}
function validateSubmission($input, $requireStudent = true) {
    $errors=[];$courses=['IS 6465','IS 6495','IS 6570'];
    $name=trim((string)($input['student_name']??''));$email=trim((string)($input['student_email']??''));
    $course=trim((string)($input['course']??''));$reason=trim((string)($input['reason']??''));
    $date=(string)($input['preferreddate']??'');$time=(string)($input['preferredtime']??'');
    $topics=$input['topics']??[];
    if(!is_array($topics)){$errors[]='Topics must be a list.';$topics=[];}
    if($requireStudent && $name==='')$errors[]='Student name is required.';
    if($requireStudent && !filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Valid student email required.';
    if(!in_array($course,$courses,true))$errors[]='Select a valid course.';
    if($msg=validatePreferredTime($time))$errors[]=$msg;
    if($msg=validateTopics($topics))$errors[]=$msg;
    if($msg=validateReason($reason))$errors[]=$msg;
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$dt || $dt->format('Y-m-d')!==$date || $dt < new DateTimeImmutable('today'))$errors[]='Choose a valid date today or later.';
    $payload=['student_name'=>$name,'student_email'=>$email,'course'=>$course,'topic'=>json_encode(['reason'=>$reason,'topics'=>array_values($topics)]),'preferred_date'=>$date,'preferred_time'=>$time];
    return [$errors,$payload];
}
function submitRequest($pdo, $input) {
    [$errors,$data]=validateSubmission($input);
    if($errors)throw new InvalidArgumentException(implode(' ',$errors));
    $pdo->beginTransaction();
    try {
        insertStudentIfAbsent($pdo,$data['student_name'],$data['student_email']);
        $student=findStudentByEmail($pdo,$data['student_email']);
        if(!$student)throw new RuntimeException('Student lookup failed');
        $id=insertTutoringRequest($pdo,(int)$student['student_id'],$data);
        $pdo->commit();return $id;
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function reviseRequest($pdo, $id, $input) {
    [$errors,$data]=validateSubmission($input,false);
    if($errors)throw new InvalidArgumentException(implode(' ',$errors));
    return updateOpenRequest($pdo,$id,$data);
}

// ---------------------------------------------------------------------
// Lab 7: authenticated request workflows and ownership rules.
// The student's identity now comes from the logged-in account, never from
// form fields, so one student cannot submit or edit requests for another.
// ---------------------------------------------------------------------
require_once dirname(__DIR__).'/services/userService.php';

function studentIdForUser($pdo, $user) {
    $s = findStudentByUserId($pdo, $user['user_id']);
    return $s ? (int)$s['student_id'] : null;
}

function submitRequestForUser($pdo, $user, $input) {
    if (!$user || $user['role'] !== 'Student') throw new AuthorizationException('Only students can request tutoring.');
    $input['student_name'] = $user['full_name'];   // ignore any posted name/email
    $input['student_email'] = $user['email'];
    [$errors,$data] = validateSubmission($input);
    if ($errors) throw new InvalidArgumentException(implode(' ',$errors));
    $pdo->beginTransaction();
    try {
        $student = ensureStudentForUser($pdo, $user);
        $id = insertTutoringRequest($pdo, (int)$student['student_id'], $data);
        $pdo->commit(); return $id;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

// Students see their own requests; administrators see all.
function listRequestsForUser($pdo, $user, $course = '', $status = '') {
    if ($user['role'] === 'Administrator') return listRequests($pdo, $course, $status);
    if ($user['role'] === 'Student') {
        $sid = studentIdForUser($pdo, $user);
        return $sid === null ? [] : listRequests($pdo, $course, $status, $sid);
    }
    throw new AuthorizationException('Your role cannot view tutoring requests yet.');
}

function canViewRequest($pdo, $user, $request) {
    if (!$user || !$request) return false;
    if ($user['role'] === 'Administrator') return true;
    return $user['role'] === 'Student' && (int)$request['student_id'] === studentIdForUser($pdo, $user);
}

function canModifyRequest($pdo, $user, $request) {
    return $user && $request && $user['role'] === 'Student'
        && (int)$request['student_id'] === studentIdForUser($pdo, $user);
}

function reviseRequestForUser($pdo, $user, $id, $input) {
    $r = findRequest($pdo, $id);
    if (!$r) return false;
    if (!canModifyRequest($pdo, $user, $r)) throw new AuthorizationException('You can only edit your own requests.');
    return reviseRequest($pdo, $id, $input);
}

function cancelRequestForUser($pdo, $user, $id) {
    $r = findRequest($pdo, $id);
    if (!$r) return false;
    if (!canModifyRequest($pdo, $user, $r)) throw new AuthorizationException('You can only cancel your own requests.');
    return cancelOpenRequest($pdo, $id);
}
