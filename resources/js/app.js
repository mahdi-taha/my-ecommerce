import './bootstrap';

import $ from 'jquery';
// window.$ = window.jQuery = $;
window.$ = $;
window.jQuery = $;

// import 'bootstrap';
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import Swal from 'sweetalert2';
window.Swal = Swal;

import select2 from 'select2';
import 'select2/dist/css/select2.min.css';
// select2($);
select2(window.$);

import './admin/sidebarmenu.js';
import './admin/app.min.js';
import './admin/simplebar.js';
// import './shop/slick.min.js';
import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

window.disableSubmitButton = function (form) {
    const button = form.querySelector('button[type="submit"]');

    button.disabled = true;

    button.querySelector('.btn-text').classList.add('d-none');
    button.querySelector('.btn-loading').classList.remove('d-none');
};
document.querySelectorAll('.toast-message')
    .forEach(toast => {

        Swal.fire({
            toast: true,
            icon: toast.dataset.type,
            title: toast.dataset.message,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5500,
            timerProgressBar: true,
        });

    });

document.querySelectorAll('.delete-form').forEach(form => {

    form.addEventListener('submit', function (e) {

        e.preventDefault();

        Swal.fire({
            title: 'Delete Item?',
            text: 'This action cannot be undone.',
            icon: 'warning',

            showCancelButton: true,

            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',

            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',

        }).then((result) => {

            if (result.isConfirmed) {

                form.submit();

            }

        });

    });

});
