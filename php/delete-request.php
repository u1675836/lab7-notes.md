<?php
// UC01 cancel (Lab 7): owner Student only, POST + CSRF. Record retained as Cancelled.
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../services/requestService.php';
$user=requireRole(['Student']);
$pdo=openDatabase();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrfOrFail();
 $id=filter_input(INPUT_POST,'request_id',FILTER_VALIDATE_INT);if(!$id||$id<1){http_response_code(400);exit('Invalid ID');}
 try{$ok=cancelRequestForUser($pdo,$user,$id);if(!$ok){http_response_code(409);exit('Request not found or not Open');}setFlash('success','Request cancelled. The record is retained.');header('Location: request-details.php?request_id='.$id,true,303);exit;}
 catch(AuthorizationException $e){http_response_code(404);exit('Request not found');}
 catch(Throwable $e){error_log($e->getMessage());http_response_code(500);exit('Unable to cancel');}
}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit('Method not allowed');}
$id=filter_input(INPUT_GET,'request_id',FILTER_VALIDATE_INT);if(!$id||$id<1){http_response_code(400);exit('Invalid ID');}
$r=findRequest($pdo,$id);if(!$r||!canModifyRequest($pdo,$user,$r)){http_response_code(404);exit('Not found');}
$pageTitle='Cancel Request';
require __DIR__.'/../includes/header.php';require __DIR__.'/../includes/nav.php';?>
<main class="container py-4"><h1>Cancel tutoring request</h1><p class="mt-3">Cancel request #<?=$id?>? The record will be retained with status Cancelled.</p>
<?php if($r['status']==='Open'):?><form method="post"><?= csrfField() ?><input type="hidden" name="request_id" value="<?=$id?>"><button class="btn btn-danger">Yes, cancel request</button></form><?php else:?><p>Only Open requests can be cancelled.</p><?php endif;?>
<a href="request-details.php?request_id=<?=$id?>">Keep request</a></main><?php require __DIR__.'/../includes/footer.php';?>
