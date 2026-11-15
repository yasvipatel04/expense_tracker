<?php
$pageTitle = $pageTitle ?? "Personal Expense Tracker";
$activeTab = $activeTab ?? "view";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="page">
    <header class="hero">
        <p class="eyebrow">Your money, in view</p>
        <h1>Personal Expense Tracker</h1>
        <p>Keep track of everyday spending, one entry at a time.</p>
    </header>

    <nav class="tabs">
        <a href="index.php?tab=add"
           class="<?= $activeTab === "add" ? "active" : "" ?>">
            ＋ Add Expense
        </a>
        <a href="index.php?tab=view"
           class="<?= $activeTab === "view" ? "active" : "" ?>">
            ☷ View Expenses
        </a>
    </nav>