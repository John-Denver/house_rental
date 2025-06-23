<?php include('db_connect.php'); ?>

<div class="container-fluid">
	<form id="add-user-form">
		<div class="form-group">
			<label for="name" class="control-label">Name</label>
			<input type="text" id="name" name="name" class="form-control" required>
		</div>
		<div class="form-group">
			<label for="username" class="control-label">Username</label>
			<input type="text" id="username" name="username" class="form-control" required>
		</div>
		<div class="form-group">
			<label for="password" class="control-label">Password</label>
			<input type="password" id="password" name="password" class="form-control" required>
		</div>
		<div class="form-group">
			<label for="type" class="control-label">User Type</label>
			<select id="type" name="type" class="form-control" required>
				<option value="" selected disabled>Select Type</option>
				<option value="1">Admin</option>
				<option value="2">Staff</option>
			</select>
		</div>
	</form>
</div>

<script>
	$('#add-user-form').submit(function(e){
		e.preventDefault();
		start_load();

		$.ajax({
			url: 'ajax.php?action=save_user',
			method: 'POST',
			data: $(this).serialize(),
			success: function(resp){
				if(resp == 1){
					alert_toast("User successfully added", 'success');
					setTimeout(function(){
						location.reload();
					}, 1500);
				} else if(resp == 2){
					alert_toast("Username already exists", 'danger');
					end_load();
				} else {
					alert_toast("An error occurred", 'danger');
					end_load();
				}
			}
		});
	});
</script>
