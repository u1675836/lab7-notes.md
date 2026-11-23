<?php
// UC01 revise (Lab 7): owner Student only, POST + CSRF, Open requests only.
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../services/requestService.php';
requirePost();
$user=requireRole(['Student']);
verifyCsrfOrFail();
$id=filter_input(INPUT_POST,'request_id',FILTER_VALIDATE_INT);if(!$id||$id<1){http_response_code(400);exit('Invalid ID');}
try{$ok=reviseRequestForUser(openDatabase(),$user,$id,$_POST);if(!$ok){http_response_code(409);exit('Request not found or not Open');}setFlash('success','Request updated.');header('Location: request-details.php?request_id='.$id,true,303);exit;}
catch(InvalidArgumentException $e){http_response_code(422);exit(escapeHtml($e->getMessage()));}
catch(AuthorizationException $e){http_response_code(404);exit('Request not found');}
catch(Throwable $e){error_log($e->getMessage());http_response_code(500);exit('Unable to update request');}
