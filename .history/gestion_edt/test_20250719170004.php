<?php
session_start();
$message = "";
$type = ""; // success | error | info

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST["email"];
    $pass = $_POST["pass"];

    if ($email === "noutechnologie@gmail.com" && $pass === "Mouad2006") {
        // Redirection après connexion réussie
        header("Location: admin.php");
        exit();
    } else {
        // Message d'erreur stocké temporairement
        $message = "❌ L'email ou le mot de passe est incorrect.";
        $type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Connexion</title>
    <style>
      .image{
        padding-bottom: 40px;
      }

      @media (max-width: 768px){
        .relative{
          display: none;
        }
      }

    </style>
  </head>
  <body>

  
        <!-- Logo -->
        
      <div class="h-screen flex items-center justify-center">
  <div class="relative flex flex-col bg-white rounded-2xl m-6 md:flex-row md:space-y-0">
    <!-- Ton contenu -->
  

        <form action="" method="post">
        <!-- left side -->
        <div class="flex flex-col justify-center p-8 md:p-14">
          <a href="#" class="logo justify-center">
            <img style="width: 200px; height: auto ; display: block; margin: 0 auto;" class="image"
                 src="/assets/images/logo.jpg" 
                 alt="Logo du site">
          </a>
          <span class="mb-3 text-4xl font-bold">Se connecter</span>
          <span class="font-light text-gray-400 mb-8">
            Bienvenue ! Veuillez saisir vos coordonnées.
          </span>
          <div class="py-4">
            <span class="mb-2 text-md">Email</span>
            <input
              type="text"
              class="w-full p-2 border border-gray-300 rounded-md placeholder:font-light placeholder:text-gray-500"
              name="email"
              id="email"
            />
          </div>
          <div class="py-4">
            <span class="mb-2 text-md">Password</span>
            <input
              type="password"
              name="pass"
              id="pass"
              class="w-full p-2 border border-gray-300 rounded-md placeholder:font-light placeholder:text-gray-500"
            />
          </div>
          
          <button
            type="submit" name="valider"
            class="w-full bg-black text-white p-2 rounded-lg mb-6 hover:bg-white hover:text-black hover:border hover:border-gray-300"
          >
           Se connecter
          </button>
      </form>
        </div>
        <!-- {/* right side */} -->
        <div class="relative">
          <img
            src="/assets/images/connexion.png"
            alt="img"
            class="h-[450px] w-full rounded-r-2xl md:block object-cover items-center justify-center"
            style="margin-top:50px"
          />
          <!-- text on image  -->
        </div>
    </div>
</div>
  </body>
</html>


