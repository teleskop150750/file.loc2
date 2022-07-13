{
    const buttons = document.querySelectorAll('.delete-item');

    const buttonHandler = async (event, button) => {
        const id = button.dataset.id;
        try {
            const response = await fetch(`/files/${id}`, {
                method: 'DELETE',
            });
            const result = await response.json();
            if (result.status === 'error') {
                console.error(result.message);
            } else {
                window.location.reload();
            }
        } catch (e) {
            console.error(e);
        }
    };

    buttons.forEach((button) => {
        button.addEventListener('click', buttonHandler.bind(this, event, button));
    })
}