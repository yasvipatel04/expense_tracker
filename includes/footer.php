</div>

<script>
const categorySelect = document.getElementById("category_id");
const customCategoryWrap = document.getElementById("customCategoryWrap");
const customCategoryInput = document.getElementById("custom_category");

function updateCustomCategoryField() {
    if (!categorySelect || !customCategoryWrap || !customCategoryInput) {
        return;
    }

    const selectedOption = categorySelect.options[categorySelect.selectedIndex];
    const isOther = selectedOption &&
        selectedOption.dataset.category.toLowerCase() === "other";

    customCategoryWrap.classList.toggle("show", Boolean(isOther));
    customCategoryInput.required = Boolean(isOther);
}

if (categorySelect) {
    categorySelect.addEventListener("change", updateCustomCategoryField);
    updateCustomCategoryField();
}
</script>

</body>
</html>