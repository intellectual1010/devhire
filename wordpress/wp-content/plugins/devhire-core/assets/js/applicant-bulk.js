document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.applicant-bulk-form');

    forms.forEach(function (form) {
        const selectAll = form.querySelector('.applicant-select-all-input');
        const applicantCheckboxes = Array.from(
            form.querySelectorAll('.applicant-select-input')
        );
        const selectedCount = form.querySelector(
            '.applicant-selected-count'
        );
        const clearSelection = form.querySelector(
            '.applicant-clear-selection'
        );
        const bulkStatus = form.querySelector('#bulk-status');
        const applyButton = form.querySelector('.applicant-bulk-apply');

        if (!selectAll || !applicantCheckboxes.length) {
            return;
        }

        function getSelectedCount() {
            return applicantCheckboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;
        }

        function updateSelectionState() {
            const checkedCount = getSelectedCount();

            selectAll.checked =
                checkedCount === applicantCheckboxes.length;

            selectAll.indeterminate =
                checkedCount > 0 &&
                checkedCount < applicantCheckboxes.length;

            if (selectedCount) {
                selectedCount.textContent =
                    checkedCount + ' selected';
            }

            if (clearSelection) {
                clearSelection.hidden = checkedCount === 0;
            }

            if (applyButton) {
                applyButton.disabled = checkedCount === 0;
            }
        }

        selectAll.addEventListener('change', function () {
            applicantCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });

            updateSelectionState();
        });

        applicantCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener(
                'change',
                updateSelectionState
            );
        });

        form.addEventListener('submit', function (event) {
            const checkedCount = getSelectedCount();

            if (checkedCount === 0) {
                event.preventDefault();
                window.alert('Select at least one applicant.');
                return;
            }

            if (!bulkStatus || !bulkStatus.value) {
                event.preventDefault();

                if (bulkStatus) {
                    bulkStatus.focus();
                }

                window.alert(
                    'Choose a status before applying the bulk action.'
                );

                return;
            }

            const sensitiveStatuses = [
                'Hired',
                'Rejected'
            ];

            if (sensitiveStatuses.includes(bulkStatus.value)) {
                const applicantWord =
                    checkedCount === 1
                        ? 'applicant'
                        : 'applicants';

                const message =
                    'Change ' +
                    checkedCount +
                    ' selected ' +
                    applicantWord +
                    ' to ' +
                    bulkStatus.value +
                    '?';

                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            }
        });

        if (clearSelection) {
            clearSelection.addEventListener('click', function () {
                applicantCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = false;
                });

                selectAll.checked = false;
                selectAll.indeterminate = false;

                updateSelectionState();
            });
        }

        updateSelectionState();
    });
});