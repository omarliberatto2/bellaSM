<?php

if(!empty($_POST)){
	if(isset($_POST["username"]) &&isset($_POST["password"])){
		if($_POST["username"]!=""&&$_POST["password"]!=""){
			include "conexion.php";
			
			$User=htmlspecialchars($_POST['username']);
			$Pass=md5(htmlspecialchars($_POST['password']));
			$user_id=null;
			$sql1= "select * from clientes where (username=\"$User\") and password=\"$Pass\" ";
			$query = $con->query($sql1);
			while ($r=$query->fetch_array()) {
				$user_id=$r["id"];
				break;
			}
//die( 'kdk '.$user_id);
			if($user_id==null){
				//die("kdk");
				print "<script>window.location='../noAutorizado.php';</script>";				
			}else{
				session_start();
				$_SESSION["user_id"]=$user_id;
				if ( $user_id > 5 || $user_id < 25 ) {
				print "<script>window.location='../index.php';</script>";
				} else {
				print "<script>window.location='../noAutorizado.php';</script>";				
				}
				
			}
		}
	}
}


 
?>