<?php
require __DIR__ . "/config/db_connection.php";

function getOrCreateCategoryId($con, $categoryName)
{
    $stmt = mysqli_prepare(
        $con,
        "SELECT category_id FROM categories WHERE category_name = ?"
    );
    mysqli_stmt_bind_param($stmt, "s", $categoryName);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $categoryId);

    if (mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        return (int)$categoryId;
    }

    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO categories (category_name) VALUES (?)"
    );
    mysqli_stmt_bind_param($stmt, "s", $categoryName);
    mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return (int)$newId;
}

function bindDynamic($stmt, $types, &$params)
{
    if ($types === "") {
        return;
    }

    $arguments = [$types];

    foreach ($params as $key => &$value) {
        $arguments[] = &$value;
    }

    call_user_func_array([$stmt, "bind_param"], $arguments);
}

$activeTab = $_GET["tab"] ?? "view";
if (!in_array($activeTab, ["add", "view"], true)) {
    $activeTab = "view";
}

$error = "";

// Add an expense
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["description"])) {
    $description = trim($_POST["description"] ?? "");
    $amount = $_POST["amount"] ?? "";
    $expenseDate = $_POST["expense_date"] ?? "";
    $categoryId = $_POST["category_id"] ?? "";
    $customCategory = trim($_POST["custom_category"] ?? "");

    if (
        $description === "" ||
        strlen($description) > 255 ||
        !is_numeric($amount) ||
        (float)$amount <= 0 ||
        $expenseDate === "" ||
        !ctype_digit((string)$categoryId)
    ) {
        $error = "Please enter valid information in every field.";
        $activeTab = "add";
    } else {
        $stmt = mysqli_prepare(
            $con,
            "SELECT category_name FROM categories WHERE category_id = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $categoryId);
        mysqli_stmt_execute($stmt);
        $selectedCategoryName = "";
        mysqli_stmt_bind_result($stmt, $selectedCategoryName);
        $categoryFound = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if (!$categoryFound) {
            $error = "Please select a valid category.";
        } elseif (strcasecmp((string)$selectedCategoryName, "Other") === 0) {
            if ($customCategory === "" || strlen($customCategory) > 100) {
                $error = "Enter a custom category name (up to 100 characters).";
            } else {
                $categoryId = getOrCreateCategoryId($con, $customCategory);
            }
        }

        if ($error === "") {
            $stmt = mysqli_prepare(
                $con,
                "INSERT INTO expenses
                    (description, amount, expense_date, category_id)
                 VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "sdsi",
                $description,
                $amount,
                $expenseDate,
                $categoryId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header("Location: index.php?tab=view&saved=1");
            exit;
        }

        $activeTab = "add";
    }
}

// Read filters
$search = trim($_GET["search"] ?? "");
$minAmount = trim($_GET["min_amount"] ?? "");
$maxAmount = trim($_GET["max_amount"] ?? "");
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");
$month = trim($_GET["month"] ?? "");
$filterCategory = trim($_GET["category_id"] ?? "");

// Build filter conditions and prepared-statement values
$where = [];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(e.description LIKE ? OR c.category_name LIKE ?)";
    $term = "%" . $search . "%";
    $params[] = $term;
    $params[] = $term;
    $types .= "ss";
}

if ($minAmount !== "" && is_numeric($minAmount)) {
    $where[] = "e.amount >= ?";
    $params[] = (float)$minAmount;
    $types .= "d";
}

if ($maxAmount !== "" && is_numeric($maxAmount)) {
    $where[] = "e.amount <= ?";
    $params[] = (float)$maxAmount;
    $types .= "d";
}

if ($dateFrom !== "") {
    $where[] = "e.expense_date >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if ($dateTo !== "") {
    $where[] = "e.expense_date <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

if ($month !== "") {
    $monthDate = DateTime::createFromFormat("!Y-m", $month);

    if ($monthDate && $monthDate->format("Y-m") === $month) {
        $monthStart = $monthDate->format("Y-m-01");
        $monthEnd = (clone $monthDate)->modify("+1 month")->format("Y-m-01");

        $where[] = "e.expense_date >= ? AND e.expense_date < ?";
        $params[] = $monthStart;
        $params[] = $monthEnd;
        $types .= "ss";
    }
}

if ($filterCategory !== "" && ctype_digit($filterCategory)) {
    $where[] = "e.category_id = ?";
    $params[] = (int)$filterCategory;
    $types .= "i";
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// Pagination: display 10 matching expenses per page
$perPage = 10;
$page = isset($_GET["page"]) && ctype_digit((string)$_GET["page"])
    ? max(1, (int)$_GET["page"])
    : 1;

$countSql = "
    SELECT COUNT(*)
    FROM expenses e
    JOIN categories c ON c.category_id = e.category_id
    $whereSql
";
$countStmt = mysqli_prepare($con, $countSql);
$countParams = $params;
bindDynamic($countStmt, $types, $countParams);
mysqli_stmt_execute($countStmt);
mysqli_stmt_bind_result($countStmt, $totalRows);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = "
    SELECT e.expense_id, e.description, e.amount, e.expense_date,
           e.category_id, c.category_name
    FROM expenses e
    JOIN categories c ON c.category_id = e.category_id
    $whereSql
    ORDER BY e.expense_date DESC, e.expense_id DESC
    LIMIT ? OFFSET ?
";
$listStmt = mysqli_prepare($con, $listSql);

$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;
$listTypes = $types . "ii";

bindDynamic($listStmt, $listTypes, $listParams);
mysqli_stmt_execute($listStmt);
$expensesResult = mysqli_stmt_get_result($listStmt);

// Categories for forms and category filtering
$categoriesResult = mysqli_query(
    $con,
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name"
);
$categories = mysqli_fetch_all($categoriesResult, MYSQLI_ASSOC);

// Dashboard summary
$statsResult = mysqli_query(
    $con,
    "SELECT
        COUNT(*) AS expense_count,
        COALESCE(SUM(amount), 0) AS total_spent,
        COALESCE(SUM(
            CASE
                WHEN expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                 AND expense_date < DATE_ADD(
                     DATE_FORMAT(CURDATE(), '%Y-%m-01'),
                     INTERVAL 1 MONTH
                 )
                THEN amount ELSE 0
            END
        ), 0) AS month_spent
     FROM expenses"
);
$stats = mysqli_fetch_assoc($statsResult);

$categoryCountResult = mysqli_query(
    $con,
    "SELECT COUNT(*) AS total FROM categories"
);
$categoryCount = mysqli_fetch_assoc($categoryCountResult)["total"];

$pageTitle = "Personal Expense Tracker";
require __DIR__ . "/includes/header.php";
?>

<section class="stats">
    <div class="stat-card">
        <div class="stat-label">Total spent</div>
        <div class="stat-value">
            $<?= number_format((float)$stats["total_spent"], 2) ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Spent this month</div>
        <div class="stat-value">
            $<?= number_format((float)$stats["month_spent"], 2) ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Expenses · Categories</div>
        <div class="stat-value">
            <?= (int)$stats["expense_count"] ?> · <?= (int)$categoryCount ?>
        </div>
    </div>
</section>

<main class="main-card">
    <div class="content">
        <?php if ($error !== ""): ?>
            <div class="notice error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET["saved"])): ?>
            <div class="notice">Expense saved successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET["updated"])): ?>
            <div class="notice">Expense updated successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET["deleted"])): ?>
            <div class="notice">Expense deleted.</div>
        <?php endif; ?>

        <?php if ($activeTab === "add"): ?>
            <h2>Add a new expense</h2>
            <p class="hint">Enter the details below to record a purchase.</p>

            <form method="post" action="index.php?tab=add">
                <div class="grid">
                    <div class="field full">
                        <label for="description">What was it for?</label>
                        <input
                            id="description"
                            name="description"
                            maxlength="255"
                            placeholder="For example, groceries"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="amount">Amount</label>
                        <input
                            id="amount"
                            type="number"
                            name="amount"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="expense_date">Date</label>
                        <input
                            id="expense_date"
                            type="date"
                            name="expense_date"
                            value="<?= date("Y-m-d") ?>"
                            required
                        >
                    </div>

                    <div class="field full">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Choose a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= (int)$category["category_id"] ?>"
                                    data-category="<?= htmlspecialchars($category["category_name"], ENT_QUOTES) ?>"
                                >
                                    <?= htmlspecialchars($category["category_name"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="custom-category" id="customCategoryWrap">
                            <label for="custom_category">New category name</label>
                            <input
                                id="custom_category"
                                name="custom_category"
                                maxlength="100"
                                placeholder="For example, Pet care"
                            >
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit">Save Expense</button>
                </div>
            </form>

        <?php else: ?>
            <h2>Your expenses</h2>
            <p class="hint">Search, filter, edit, or remove your recorded expenses.</p>

            <form method="get" action="index.php">
                <input type="hidden" name="tab" value="view">

                <div class="filter-grid">
                    <div class="field">
                        <label for="search">Search</label>
                        <input
                            id="search"
                            name="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Description or category"
                        >
                    </div>

                    <div class="field">
                        <label for="category_filter">Category</label>
                        <select id="category_filter" name="category_id">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= (int)$category["category_id"] ?>"
                                    <?= $filterCategory == $category["category_id"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($category["category_name"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="month">Month</label>
                        <input
                            id="month"
                            type="month"
                            name="month"
                            value="<?= htmlspecialchars($month) ?>"
                        >
                    </div>

                    <div class="field">
                        <label for="min_amount">Minimum amount</label>
                        <input
                            id="min_amount"
                            type="number"
                            name="min_amount"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars($minAmount) ?>"
                        >
                    </div>

                    <div class="field">
                        <label for="max_amount">Maximum amount</label>
                        <input
                            id="max_amount"
                            type="number"
                            name="max_amount"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars($maxAmount) ?>"
                        >
                    </div>

                    <div class="field">
                        <label for="date_from">Date from</label>
                        <input
                            id="date_from"
                            type="date"
                            name="date_from"
                            value="<?= htmlspecialchars($dateFrom) ?>"
                        >
                    </div>

                    <div class="field">
                        <label for="date_to">Date to</label>
                        <input
                            id="date_to"
                            type="date"
                            name="date_to"
                            value="<?= htmlspecialchars($dateTo) ?>"
                        >
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit">Apply filters</button>
                    <a class="button teal" href="index.php?tab=view">Clear filters</a>
                </div>
            </form>

            <p>
                Showing <?= mysqli_num_rows($expensesResult) ?>
                of <?= (int)$totalRows ?> matching expenses.
            </p>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($expensesResult) > 0): ?>
                        <?php while ($expense = mysqli_fetch_assoc($expensesResult)): ?>
                            <tr>
                                <td><?= htmlspecialchars($expense["description"]) ?></td>
                                <td>$<?= number_format((float)$expense["amount"], 2) ?></td>
                                <td><?= htmlspecialchars($expense["expense_date"]) ?></td>
                                <td>
                                    <span class="pill">
                                        <?= htmlspecialchars($expense["category_name"]) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="edit-link"
                                           href="edit_expense.php?id=<?= (int)$expense["expense_id"] ?>">
                                            Edit
                                        </a>

                                        <form method="post" action="delete_expense.php"
                                              onsubmit="return confirm('Delete this expense?');">
                                            <input
                                                type="hidden"
                                                name="expense_id"
                                                value="<?= (int)$expense["expense_id"] ?>"
                                            >
                                            <button class="delete" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No expenses match these filters.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <?php
                $paginationFilters = $_GET;
                unset($paginationFilters["page"]);
                ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <?php
                        $previousFilters = $paginationFilters;
                        $previousFilters["page"] = $page - 1;
                        ?>
                        <a href="index.php?<?= htmlspecialchars(http_build_query($previousFilters)) ?>">
                            Previous
                        </a>
                    <?php endif; ?>

                    <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>

                    <?php if ($page < $totalPages): ?>
                        <?php
                        $nextFilters = $paginationFilters;
                        $nextFilters["page"] = $page + 1;
                        ?>
                        <a href="index.php?<?= htmlspecialchars(http_build_query($nextFilters)) ?>">
                            Next
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . "/includes/footer.php"; ?>