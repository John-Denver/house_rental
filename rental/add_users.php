<?php include('db_connect.php'); ?>

<div class="container-fluid">
	<div class="card">
		<div class="card-body">
			<h4 class="card-title mb-4">Add New User</h4>
			<form id="add-user-form">
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label for="name" class="form-label">Full Name</label>
							<input type="text" id="name" name="name" class="form-control" required>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label for="username" class="form-label">Login Username</label>
							<input type="text" id="username" name="username" class="form-control" required>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label for="password" class="form-label">Login Password</label>
							<input type="password" id="password" name="password" class="form-control" required>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label for="type" class="form-label">User Type</label>
							<select id="type" name="type" class="form-control" required>
								<option value="" selected disabled>Select Type</option>
								<option value="1">Admin</option>
								<option value="2">Staff</option>
								<option value="3">Landlord</option>
								<option value="4">Customer</option>
								<option value="5">Caretaker</option>
							</select>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label for="type" class="form-label">User Type</label>
							<select id="type" name="type" class="form-control" required>
								<option value="" selected disabled>Select Type</option>
								<option value="1">Admin</option>
								<option value="2">Staff</option>
								<option value="3">Landlord</option>
								<option value="4">Customer</option>
								<option value="5">Caretaker</option>
							</select>
						</div>
					</div>
				</div>
				
				<div class="text-right mt-3">
					<button type="submit" class="btn btn-primary">Save User</button>
					<button type="button" class="btn btn-secondary" onclick="$('.modal').modal('hide')">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	function validateForm() {
		let password = $('#password').val();
		
		if (password.length < 8) {
			alert_toast('Password must be at least 8 characters long', 'danger');
			return false;
		}
		
		return true;
	}

	$('#add-user-form').submit(function(e){
		e.preventDefault();
		
		if (!validateForm()) {
			return;
		}
		
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
				} else if(resp == 3){
					alert_toast("Error: " + resp, 'danger');
					end_load();
				}
			}
		});
	});
</script>
