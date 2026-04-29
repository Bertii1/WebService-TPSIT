document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-elimina').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('Sei sicuro di voler eliminare questo elemento?')) {
                e.preventDefault();
            }
        });
    });
});
