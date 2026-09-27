// resources/js/reports/index.js

document.addEventListener('DOMContentLoaded', function () {
    const exportButtons = document.querySelectorAll('.export-button');

    exportButtons.forEach(button => {
        button.addEventListener('click', function () {
            const format = this.dataset.format;
            const reportId = this.dataset.reportId;

            fetch(`/reports/export/${reportId}?format=${format}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                },
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.blob();
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `report.${format}`;
                document.body.appendChild(a);
                a.click();
                a.remove();
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
        });
    });
});