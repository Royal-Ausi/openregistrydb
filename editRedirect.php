<?php
session_start();

if (!isset($_SESSION["username"])) {
  header("Location: user_login.php");
  exit();
}

$modules = $_SESSION["assigned_modules"] ?? [];

if (count($modules) === 1) {
  // Redirect directly
  $target = strtolower(str_replace(" ", "_", $modules[0])) . "_edit.php";
  header("Location: modules/$target");
  exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Select Module to Edit</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-5">
    <h4>Select a Module to Edit</h4>
    <form method="POST" action="edit_redirect.php">
      <div class="mb-3">
        <label for="moduleSelect">Choose Module</label>
        <select name="selected_module" id="moduleSelect" class="form-control" required>
          <?php foreach ($modules as $mod): ?>
            <option value="<?= $mod ?>"><?= $mod ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Proceed</button>
    </form>
  </div>
</body>
</html>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $selected = $_POST["selected_module"];
  $target = strtolower(str_replace(" ", "_", $selected)) . "_edit.php";
  header("Location: modules/$target");
  exit();
}
?>