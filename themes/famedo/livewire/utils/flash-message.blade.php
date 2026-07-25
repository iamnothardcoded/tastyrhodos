{{-- famedo theme override of igniter-orange::livewire.utils.flash-message (forked from ti-theme-orange v4.1.3) --}}
{{-- Changes vs stock: TOASTS always auto-hide (4.5s) and the stack is capped
     at 6 (oldest drops first) — stock honored message.important with
     autohide=false, so repeated cart rejections piled up top-center and never
     left. OVERLAY messages (modal branch) keep stock behavior incl. important:
     those are deliberate blockers (e.g. CSRF expiry), not transient notices. --}}
<div>
    <div
        x-data='OrangeFlashMessage(@json($messages))'
        id="toast-notification"
        class="toast-container position-fixed p-3 top-0 start-50 translate-middle-x"
    ></div>
</div>
@script
<script>
    window.OrangeFlashMessage = (messages) => {
        return {
            messages: messages,
            overlayOptions: {
                backdrop: 'static',
                keyboard: false,
            },
            buildMessage: function (message) {
                if (message.overlay) {
                    var modal = this.buildOverlayModal(message);
                    document.body.appendChild(modal);
                    var modalElement = new bootstrap.Modal(modal, message.important ? self.overlayOptions : {});
                    modalElement.show();
                } else {
                    var container = document.getElementById('toast-notification');
                    var toast = this.buildToast(message);
                    container.appendChild(toast);
                    // Cap the stack at 6 — oldest out first.
                    var stack = container.querySelectorAll('.toast');
                    for (var i = 0; i < stack.length - 6; i++) {
                        var inst = bootstrap.Toast.getInstance(stack[i]);
                        if (inst) inst.dispose();
                        stack[i].remove();
                    }
                    var toastElement = new bootstrap.Toast(toast, {autohide: true, delay: 4500});
                    toastElement.show();
                }
            },
            buildToast: function (message) {
                var toast = document.createElement('div');
                toast.classList.add('toast', 'fade', 'align-items-center', 'text-bg-'+message.level, 'border-'+message.level);
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');

                var toastBody = document.createElement('div');
                toastBody.classList.add('d-flex');
                toast.appendChild(toastBody);

                var toastMessage = document.createElement('div');
                toastMessage.classList.add('toast-body');
                toastMessage.innerHTML = message.message;
                toastBody.appendChild(toastMessage);

                var toastClose = document.createElement('button');
                toastClose.classList.add('btn-close', 'me-2', 'm-auto');
                toastClose.setAttribute('type', 'button');
                toastClose.setAttribute('data-bs-dismiss', 'toast');
                toastClose.setAttribute('aria-label', 'Close');
                toastBody.appendChild(toastClose);

                return toast;
            },
            buildOverlayModal: function (message) {
                var modal = document.createElement('div');
                modal.classList.add('modal', 'fade', 'animated', 'fadeInDown');
                modal.setAttribute('wire:ignore', '-1');
                modal.setAttribute('tabindex', '-1');
                modal.setAttribute('aria-labelledby', 'flash-message-modal');
                modal.setAttribute('aria-hidden', 'true');

                var modalDialog = document.createElement('div');
                modalDialog.classList.add('modal-dialog', 'modal-dialog-centered');
                modal.appendChild(modalDialog);

                var modalContent = document.createElement('div');
                modalContent.classList.add('modal-content');
                modalDialog.appendChild(modalContent);

                var modalHeader = document.createElement('div');
                modalHeader.classList.add('modal-header', 'border-0');
                modalContent.appendChild(modalHeader);

                var modalTitle = document.createElement('h5');
                modalTitle.classList.add('modal-title');
                modalTitle.innerHTML = message.title;
                modalHeader.appendChild(modalTitle);

                var modalBody = document.createElement('div');
                modalBody.classList.add('modal-body');
                modalBody.innerHTML = message.message;
                modalContent.appendChild(modalBody);

                var modalFooter = document.createElement('div');
                modalFooter.classList.add('modal-footer', 'border-0');
                modalContent.appendChild(modalFooter);

                var modalOk = document.createElement('a');
                modalOk.classList.add('btn', 'btn-primary');
                modalOk.setAttribute('type', 'button');
                if (message.actionUrl) {
                    modalOk.setAttribute('href', message.actionUrl);
                } else {
                    modalOk.setAttribute('data-bs-dismiss', 'modal');
                }
                modalOk.textContent = message.actionText ?? 'Ok';
                modalFooter.appendChild(modalOk);

                return modal;
            },
            init: function () {
                this.messages.forEach((message) => {
                    this.buildMessage(message);
                });
            },
        }
    }
</script>
@endscript
