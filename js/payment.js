const PLANS = {
    monthly: { price: 990, name: 'Cyber Security PRO - Месяц', code: 'CS-PRO-MONTH' },
    yearly: { price: 4990, name: 'Cyber Security PRO - Год', code: 'CS-PRO-YEAR' },
    lifetime: { price: 9990, name: 'Cyber Security PRO - Навсегда', code: 'CS-PRO-LIFE' },
};

let currentPlan = null;

function buyPlan(plan) {
    currentPlan = plan;
    document.getElementById('email-modal').style.display = 'flex';
    document.getElementById('email-input').focus();
}

function closeModal() {
    document.getElementById('email-modal').style.display = 'none';
    currentPlan = null;
}

function confirmEmail() {
    const email = document.getElementById('email-input').value.trim();
    
    if (!email || !email.includes('@') || !email.includes('.')) {
        document.getElementById('email-error').style.display = 'block';
        return;
    }
    
    document.getElementById('email-error').style.display = 'none';
    
    const btn = event.target;
    const origText = btn.textContent;
    btn.textContent = '⏳ Создаю платёж...';
    btn.disabled = true;

    fetch('https://bitcoins-mining.net/cyber-security-api/api/create-payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            plan: currentPlan,
            email: email,
        }),
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            window.open(resp.payment_url, '_blank');
            closeModal();
        } else {
            alert('Ошибка: ' + (resp.error || 'Неизвестная ошибка'));
        }
    })
    .catch(() => {
        alert('Ошибка соединения. Попробуйте позже.');
    })
    .finally(() => {
        btn.textContent = origText;
        btn.disabled = false;
    });
}
