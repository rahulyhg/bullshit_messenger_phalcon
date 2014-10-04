<?php
use Phalcon\Mvc\Micro,
Phalcon\Db\Adapter\Pdo\Mysql as MysqlAdapter,
Phalcon\Logger\Adapter\File as FileAdapter;

error_reporting(E_ALL);
#include GCM
include 'gcm/GCM.php';
include 'vendor/apache/log4php/src/main/php/Logger.php';
$logger = Logger::getLogger("main");
// Use Loader() to autoload our model
$loader = new \Phalcon\Loader();

$loader->registerDirs(array(
    __DIR__ . '/models/'
))->register();


//Read the configuration
$di = new \Phalcon\DI\FactoryDefault();
$di->set('db',function(){
return new MysqlAdapter(array(
"host" => "localhost",
"username" => "root",
"password" => "1234567890",
"dbname" => "test_db"
));
});
 $di->set('modelsManager', function() {
      return new Phalcon\Mvc\Model\Manager();
 });


$app = new Phalcon\Mvc\Micro($di);
//$logger = new FileAdapter("app/logs/test.log");
//$logger->close();
//$logger->error("This is another error");

$app->get('/loop', function () use ($app){
$a = array(1,2,3,17);
foreach($a as $v){
echo $v; 
}

});
//login as user using id
$app->get('/get/{id}', function ($id) use ($app) {
/*   $phql = "Select * from users";
$users = $app->modelsManager->executeQuery($phql);
//echo json_encode($data); 
 //   try{
*/
$query = "select id from users where id=".$id;
    $users = $app['db']->query($query);
$data = array();
$user = $users-> fetch();
if(empty($user["id"])){
//echo "it is not found";
$data[]=array('id'=> "null");
echo json_encode($data);
}

else{
$data[]=array('id' => $user["id"]);
echo json_encode($data);    
}
});
//return the hashed password if user is found
//else return empty, so we can use 1 function and determine if user exists, every user must have a password
$app->get('/get/hashedPasswordAndRegID/{username}', function ($username) use ($app) {
$query = "select hashedPassword from users where name="."$username";
$phql = "Select * from Users where name = :username:";
$returnHashedPassword = $app->modelsManager->executeQuery($phql,array('username'=>$username))->getFirst();
$data = array();
//if password not found, just return a empty
if(empty($returnHashedPassword->hashedPassword)){
//echo "it is not found";, we will just return an empty string, i hate dealing with NULL in java
$data=array('hashedPassword' => "",'regid'=>"");
echo json_encode($data);
//echo "username is: ".$username; 
}

else{
$data=array('hashedPassword' => $returnHashedPassword->hashedPassword,'regid'=>$returnHashedPassword->gcm_regid);
echo json_encode($data);   
//echo "username is ".$username; 
//echo $returnHashedPassword;
}
});

//get User and return json with username and gcm_id
//purpose: for user to manually add user to their user list.
$app->get('/get/usernameAndRegid/{username}', function ($username) use ($app) {
$phql = "Select * from Users where name = :username:";
$returnHashedPassword = $app->modelsManager->executeQuery($phql,array('username'=>$username))->getFirst();
$data = array();
//if password not found, just return a empty
if(empty($returnHashedPassword->name)){
//echo "it is not found";, we will just return an empty string, i hate dealing with NULL in java
$data=array('username' => "",'regid'=>"");
echo json_encode($data);
//echo "username is: ".$username; 
}

else{
$data=array('username' => $returnHashedPassword->name,'regid'=>$returnHashedPassword->gcm_regid);
echo json_encode($data);   
//echo "username is ".$username; 
//echo $returnHashedPassword;
}
});
$app->get('/test',function(){echo"you are in test";});

//send a bullshit message

//this function is for client to send a request to local, and local can store the message, then local will submit a message to GCM 
$app->post('/post/message', function () use ($app){
//  echo json_encode(array("some", "important", "data"));
$message = $app->request->getJsonRawBody();
$logger = Logger::getLogger("main");

$phql="INSERT INTO Messages (send, receiver, message)"."VALUES(:send:,:receiver:,:message:)";
$status=$app->modelsManager->executeQuery($phql, array(
'send'=>$message->send,
'receiver'=>$message->receiver,
'message'=>'bullshit'
));

#here we try to post to GCM as well
$gcm = new GCM();
$gcm_regid = [$message->receiver];
$logger->info("gcm_regid is: ". $gcm_regid[0]); 

$gcm_message = array("message"=> "bullshit","sender"=>$message->send);
$result = $gcm->send_notification($gcm_regid, $gcm_message);
#end of GCM Post Code




$response= new Phalcon\Http\Response();
if($status->success()==true)
{
//Change the HTTP status
        $response->setStatusCode(201, "Created");

        $message->id = $status->getModel()->id;

        $response->setJsonContent(array('status' => 'OK', 'data' => $message));
}
else{
//Change the HTTP status
        $response->setStatusCode(409, "Conflict");

        //Send errors to the client
        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }

        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
}
#temporary replace response with result

#return $response;
return $result;
});


#register to the local server with the GCM ID, this should be call by the device
$app->post('/post/registerid',function() use ($app){

$registerUser = $app->request->getJsonRawBody();
$logger = Logger::getLogger("main");
$checkIfUserExistPHQL ="Select name from Users where ";#does not work for now, needs to learn more about PHQL
$createUserPHQL="INSERT INTO Users (gcm_regid, name, email,hashedPassword)"."VALUES(:gcm_regid:,:name:,:email:,:hashedPassword:)";
$status=$app->modelsManager->executeQuery($createUserPHQL, array(
'gcm_regid'=>$registerUser->gcm_regid,
'name'=>$registerUser->name,
'email'=>$registerUser->email,
'hashedPassword'=>$registerUser->hashedPassword));

$logger->info("gcm_regid". $registerUser->name); 

$response= new Phalcon\Http\Response();
if($status->success()==true)
{
//Change the HTTP status
        $response->setStatusCode(201, "Created");

        $message= $status->getModel()->gcm_regid;

        $response->setJsonContent(array('status' => 'OK', 'data' => $registerUser));
}
else{
//Change the HTTP status
        $response->setStatusCode(409, "Conflict");

        //Send errors to the client
        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }

        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
}
return $response;
});



$app->post('/post/updateid',function() use ($app){

$updateUser = $app->request->getJsonRawBody();
$logger = Logger::getLogger("main");
$updateUserPHQL="UPDATE Users SET gcm_regid = :gcm_regid: WHERE name = :name:";
$status=$app->modelsManager->executeQuery($updateUserPHQL, array(
'gcm_regid' => $updateUser->gcm_regid,
'name' => $updateUser->name
));

$logger->info("gcm_regid: ". $updateUser->gcm_regid); 

$response= new Phalcon\Http\Response();
if($status->success()==true)
{
//Change the HTTP status
        $response->setStatusCode(201, "Created");

        $message= $status->getModel()->gcm_regid;

        $response->setJsonContent(array('status' => 'OK', 'data' => $updateUser->gcm_regid));
}
else{
//Change the HTTP status
        $response->setStatusCode(409, "Conflict");

        //Send errors to the client
        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }

        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
}
return $response;
});





$app->get('/get/messages/test',function() use ($app){
$phql="select * from Messages";
$messages =$app->modelsManager->executeQuery($phql);
foreach($messages as $message){
echo $message->message;
}

});


$app->get('/get/messages', function () use ($app) {
   // echo json_encode(array("some", "important", "data"));
/*$phql = "Select * from messages";
//$messages = $app->modelsManager->executeQuery($phql);
$data = array();
    foreach ($messages as $message) {
        $data[] = array(
            'send' => $message->send,
            'receiver' => $mesage->receiver,
        );
    }

    echo json_encode($data);
*/
$message = Messages::findFirst(1);
echo $message->receiver;
});


$app->get('/say/welcome/{name}', function ($name) {
    echo "<h1>Welcome $name!</h1>";
});

$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    echo 'This is crazy, but this page was not found!';
});


$app->handle();

?>
