<?php
ini_set('display_errors', 'Off');
error_reporting(E_ALL);

include_once 'xc_config.php';
include_once 'xc_functions.php';

$fn = new Functions();

$response = array();

if (isset($_GET['action'])) {
    
    $action = $_GET['action'];
    $username = $_GET['username'];
    $password =$_GET['password'];
    
    if ($action == "get_live_streams") {
       $res = $fn->getLiveStreams($username, $password, "live");
        echo json_encode($res);
    } else if ($action == "get_live_categories") {
        $res = $fn->getCategories($username, $password, "live");
        echo json_encode($res);
    } else if ($action == "get_vod_streams") {
        $res = $fn->getMovieStreams($username, $password, "movie");
        echo json_encode($res);
    } else if ($action == "get_vod_categories") {
        $res = $fn->getCategories($username, $password, "movie");
        echo json_encode($res);
    } else if ($action == "get_vod_info") {
        $void_id = $_GET["void_id"];
        $movie_info = $fn->getMovieInfo($username, $password, $vod_id);
        $response ["info"] = $movie_info;
        $response ["movie_data"] = $fn->getMovieData($void_id);
       
        echo json_encode($response);
        
    } else if ($action == "get_series") {
        
    } else if ($action == "get_series_categories") {
        
    }
} else {

    if (isset($_GET['username']) && isset($_GET['password'])) {
        $res = array();
        $username = $_GET['username'];
        $password =$_GET['password'];
        $userinfo = $fn->getUserInfo($username, $password);
        $response ["user_info"] = $userinfo;
        $response ["server_info"] = $fn->getServerInfo();
        echo json_encode($response);
    }else{
        $response ["message"] = "Username or Password missing!";
    }
    
}
?>
