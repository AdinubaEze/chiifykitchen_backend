<?php
namespace App\Http\Controllers;
class WelcomeController{
  public function welcome(){
     return response()->json([
        "title"=>"Welcome To Chiify Kitchen",
        "description"=>"A cozy, modern dining spot where great taste meets tradition. Enjoy delicious meals, effortless table bookings, and a seamless QR code ordering experience—all designed to make your visit memorable.",
        "company"=>"ChiifyKitchen",
        "developer"=>array("fullname"=>"Emmanuel Eze Adinuba", "email"=>"mradinuba@gmail.com", "youtube"=>"https://www.youtube.com/@mradinuba")
     ]);
  }
}