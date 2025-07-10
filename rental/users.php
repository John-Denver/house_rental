<?php 
?>

<div class="container-fluid">

	<div class="row">
		<div class="col-lg-12">
			<button class="btn btn-primary float-right btn-sm" id="new_user"><i class="fa fa-plus"></i> New user</button>
		</div>
	</div>
	<br>
	<div class="row">
		<div class="card col-lg-12">
			<div class="card-body">
				<!-- Add User Button Above Search -->
				<div class="mb-3">
					<button class="btn btn-success btn-sm" id="add_user_top"><i class="fa fa-user-plus"></i> Add User</button>
				</div>
				<table class="table table-striped table-bordered col-md-12" id="user_table">
					<thead>
						<tr>
							<th class="text-center">#</th>
							<th class="text-center">Name</th>
							<th class="text-center">Username</th>
							<th class="text-center">Type</th>
							<th class="text-center">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php
							include 'db_connect.php';
							// Get user type - handles both numeric and string types
function get_user_type($type) {
    // If it's already a string, return it as is
    if (is_string($type)) {
        return $type;
    }
    
    // If it's numeric, convert using mapping
    $types = array(
        1 => 'admin',
        2 => 'staff',
        3 => 'landlord',
        4 => 'customer',
        5 => 'caretaker'
    );
    
    return isset($types[$type]) ? $types[$type] : '';
}
							$users = $conn->query("SELECT * FROM users ORDER BY name ASC");
							$i = 1;
							while($row= $users->fetch_assoc()):
						?>
						<tr>
							<td class="text-center"><?php echo $i++ ?></td>
							<td><?php echo ucwords($row['name']) ?></td>
							<td><?php echo $row['username'] ?></td>
							<td><?php echo ucfirst(get_user_type($row['type'])) ?></td>
							<td>
								<center>
									<div class="btn-group">
										<button type="button" class="btn btn-primary">Action</button>
										<button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
											<span class="sr-only">Toggle Dropdown</span>
										</button>
										<div class="dropdown-menu">
											<a class="dropdown-item edit_user" href="javascript:void(0)" data-id='<?php echo $row['id'] ?>'>Edit</a>
											<div class="dropdown-divider"></div>
											<a class="dropdown-item delete_user" href="javascript:void(0)" data-id='<?php echo $row['id'] ?>'>Delete</a>
										</div>
									</div>
								</center>
							</td>
						</tr>
						<?php endwhile; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

</div>

<script>
	$(document).ready(function(){
		$('#user_table').dataTable();

		$('#new_user').click(function(){
			uni_modal('New User','manage_user.php')
		});

		$('#add_user_top').click(function(){
			uni_modal('Add User','add_users.php')
		});

		$('.edit_user').click(function(){
			uni_modal('Edit User','manage_user.php?id='+$(this).attr('data-id'))
		});

		$('.delete_user').click(function(){
			_conf("Are you sure to delete this user?","delete_user",[$(this).attr('data-id')])
		});
	});

	function delete_user($id){
		start_load()
		$.ajax({
			url:'ajax.php?action=delete_user',
			method:'POST',
			data:{id:$id},
			success:function(resp){
				if(resp==1){
					alert_toast("Data successfully deleted",'success')
					setTimeout(function(){
						location.reload()
					},1500)
				}
			}
		})
	}
</script>
