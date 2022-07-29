{
    const form = document.querySelector('form');
    const alert = document.querySelector('.alert');
    const alertMessage = alert.querySelector('.alert-message');
    const input = document.querySelector('input[type="file"]')

    const sendForm = async (event) => {
        event.preventDefault();
        alert.classList.add('hidden');
        try {
            const formData = new FormData(form);
            // formData.append('file', input.files[0])
            const response = await fetch('/file-manager', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.status === 'error') {
                alertMessage.textContent = result.message;
                alert.classList.remove('hidden');
            } else {
                window.location.href = '/files';
            }
        } catch (e) {
            console.error(e);
        }
    };

    form.addEventListener('submit', sendForm);
}
