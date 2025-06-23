<?php 
include('db_connect.php');
session_start();

// Initialize meta array
$meta = [];

// Check if id is set and is numeric
if(isset($_GET['id']) && is_numeric($_GET['id'])){
    $id = (int)$_GET['id'];
    
    // Using prepared statement
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    if($stmt){
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result && $result->num_rows > 0){
            $meta = $result->fetch_assoc();
        } else {
            // Handle case where user doesn't exist
            die('<div class="alert alert-danger">User not found</div>');
        }
        $stmt->close();
    } else {
        die('<div class="alert alert-danger">Database error</div>');
    }
}
?>
<div class="container-fluid">
    <div id="msg"></div>
    
    <form action="" id="manage-user">    
        <input type="hidden" name="id" value="<?php echo isset($meta['id']) ? htmlspecialchars($meta['id']) : '' ?>">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" 
                   value="<?php echo isset($meta['name']) ? htmlspecialchars($meta['name']) : '' ?>" required>
        </div>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" class="form-control" 
                   value="<?php echo isset($meta['username']) ? htmlspecialchars($meta['username']) : '' ?>" required autocomplete="off">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" class="form-control" value="" autocomplete="off">
            <?php if(isset($meta['id'])): ?>
            <small><i>Leave this blank if you don't want to change the password.</i></small>
            <?php endif; ?>
        </div>
        <?php if(isset($meta['type']) && $meta['type'] == 3): ?>
            <input type="hidden" name="type" value="3">
        <?php else: ?>
            <?php if(!isset($_GET['mtype'])): ?>
            <div class="form-group">
                <label for="type">User Type</label>
                <select name="type" id="type" class="custom-select">
                    <option value="2" <?php echo isset($meta['type']) && $meta['type'] == 2 ? 'selected' : '' ?>>Staff</option>
                    <option value="1" <?php echo isset($meta['type']) && $meta['type'] == 1 ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </form>
</div>
<script>
    $('#manage-user').submit(function(e){
        e.preventDefault();
        start_load();
        
        // Add confirmation for password change if editing
        <?php if(isset($meta['id'])): ?>
        if($('#password').val().length > 0 && !confirm("Are you sure you want to change the password?")) {
            end_load();
            return false;
        }
        <?php endif; ?>
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'ajax.php?action=save_user',
            method: 'POST',
            data: formData,
            success: function(resp){
                if(resp == 1){
                    alert_toast("Data successfully saved",'success');
                    setTimeout(function(){
                        location.reload();
                    },1500);
                } else if(resp == 2) {
                    $('#msg').html('<div class="alert alert-danger">Username already exists</div>');
                    end_load();
                } else {
                    $('#msg').html('<div class="alert alert-danger">Error: ' + resp + '</div>');
                    end_load();
                }
            },
            error: function(xhr, status, error) {
                $('#msg').html('<div class="alert alert-danger">Request failed. Error: ' + error + '</div>');
                end_load();
            }
        });
    });
</script>

<footer class="footer d-flex flex-column flex-md-row align-items-center justify-content-between px-4 py-3 border-top small">
   <p class="text-muted mb-1 mb-md-0">Copyright © 2025 <a href="https://github.com/John-Denver/" target="_blank">House Rental</a> - Design Thiira and Tallam, Backend - Denver</p>
</footer>