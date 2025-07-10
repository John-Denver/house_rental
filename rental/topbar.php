<style>
   .logo {
       font-size: 50px;
       background: white;
       padding: 7px 11px;
       border-radius: 50% 50%;
       color: #000000b3;
   }
   .compact-navbar {
       min-height: 1.25rem !important;
       padding: 0;
   }
   .compact-navbar img.logo-admin {
       width: 100px !important; 
       height: 100px !important; 
   }
   .compact-navbar .profile-img {
       width: 30px !important;
       height: 30px !important;
   }
</style>

<nav class="navbar navbar-light fixed-top bg-white compact-navbar">
   <div class="container-fluid h-100 py-1">
      <div class="d-flex justify-content-between align-items-center w-100 h-100">
         <!-- Logo -->
         <div class="d-flex align-items-center">
            <a href="index.php" class="logo logo-admin">
               <img src="assets/img/smart_rental_logo.png" alt="logo" class="logo-admin">
            </a>
         </div>
         
         <!-- Profile image-->
         <div class="d-flex align-items-center h-100">
            <div class="dropdown">
               <a href="#" class="text-dark" id="account_settings" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <img src="assets/img/img.jpg" class="rounded-circle profile-img">
               </a>
               <div class="dropdown-menu p-0" aria-labelledby="account_settings" style="left: -2.5em;">
                  <div class="dropdown-item noti-title profile-dropdown">
                     <h5 class="text3">Welcome</h5>
                  </div>
                  <a class="dropdown-item" href="javascript:void(0)" id="manage_my_account"><i class="fa fa-cog text-muted"></i> Manage Account</a>
                  <a class="dropdown-item" href="../logout.php"><i class="fa fa-power-off text-muted"></i> Logout</a>
               </div>
            </div>
         </div>
      </div>
   </div>
</nav>


<script>
   $('#manage_my_account').click(function(){
     uni_modal("Manage Account","manage_user.php?id=<?php echo $_SESSION['user_id'] ?>&mtype=own")
   })
</script>

<script language="javascript">
    var today = new Date();
    document.getElementById('time').innerHTML = today;
</script>

<script type="text/javascript">
function googleTranslateElementInit() {
  new google.translate.TranslateElement({pageLanguage: 'en'}, 'google_translate_element');
}
</script>

<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

<script type="text/javascript">
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en'
        }, 'google_translate_element');

        var $googleDiv = $("#google_translate_element .skiptranslate");
        var $googleDivChild = $("#google_translate_element .skiptranslate div");
        $googleDivChild.next().remove();

        $googleDiv.contents().filter(function() {
            return this.nodeType === 3 && $.trim(this.nodeValue) !== '';
        }).remove();

    }
</script>
<style>
    .goog-te-gadget .goog-te-combo {
        margin: 0px 0;
        padding: 8px;
        color: #000;
        background: #eeee;
    }
</style>