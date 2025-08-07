function initAutosave({ tableName, triggerColumnIndex }) {
  document.querySelectorAll("input").forEach(input => {
    input.addEventListener("blur", function () {
      const rowIndex = this.dataset.row;
      const colIndex = parseInt(this.dataset.col);

      if (colIndex === triggerColumnIndex) {
        const rowInputs = document.querySelectorAll(`input[data-row='${rowIndex}']`);
        const rowData = Array.from(rowInputs).map(i => i.value.trim());

        fetch("../includes/autosave.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ table: tableName, row: rowData })
        })
        .then(res => res.text())
        .then(response => {
          console.log("Autosave:", response);
          if (response.includes("✅")) {
            rowInputs.forEach(i => i.parentElement.parentElement.classList.add("row-saved"));
          } else {
            alert(response);
          }
        })
        .catch(err => console.error("Autosave error:", err));
      }
    });
  });
}