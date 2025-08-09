document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const feedbackEl = document.getElementById('feedback');
    const data = { email: document.getElementById('email').value };

    feedbackEl.textContent = 'Envoi en cours...';
    feedbackEl.style.color = '#333';

    fetch('../api/auth/request_reset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            feedbackEl.style.color = '#22c55e'; // Vert
        } else {
            feedbackEl.style.color = '#ef4444'; // Rouge
        }
        feedbackEl.textContent = result.message;
    });
});