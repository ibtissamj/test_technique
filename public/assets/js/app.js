// Gestion des onglets
document.addEventListener('DOMContentLoaded', () => {
    // Initialisation des onglets
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');

            // Retirer les classes actives
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));

            // Ajouter la classe active
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });

    // Gestion du formulaire de conversion
    const converterForm = document.getElementById('converter-form');
    if (converterForm) {
        converterForm.addEventListener('submit', handleCurrencyConversion);
    }

    // Gestion du formulaire IBAN
    const ibanForm = document.getElementById('iban-form');
    if (ibanForm) {
        ibanForm.addEventListener('submit', handleIbanValidation);
    }

    // Gestion du formulaire de prêt
    const loanForm = document.getElementById('loan-form');
    if (loanForm) {
        loanForm.addEventListener('submit', handleLoanCalculation);
    }

    // Format automatique de l'IBAN pendant la saisie
    const ibanInput = document.getElementById('iban-input');
    if (ibanInput) {
        ibanInput.addEventListener('input', (e) => {
            const value = e.target.value.replace(/\s/g, '').toUpperCase();
            const formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
        });
    }
});

// Fonction de conversion de devises
async function handleCurrencyConversion(e) {
    e.preventDefault();

    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const resultDiv = document.getElementById('converter-result');
    const errorDiv = document.getElementById('error-message');

    // Désactiver le bouton et afficher un loader
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="loading"></span> Conversion en cours...';

    // Récupérer les données du formulaire
    const formData = new FormData(form);
    const data = {
        amount: formData.get('amount'),
        from_currency: formData.get('from_currency'),
        to_currency: formData.get('to_currency')
    };

    try {
        // TODO: Implémenter l'appel API vers /api/convert.php via CurrencyController
        const response = await fetch('/api/convert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (response.ok && result.success) {
            // Afficher le résultat (adapter selon la structure de réponse du BaseController)
            const data_result = result.data || result;
            resultDiv.innerHTML = `
                <h3>Résultat de la conversion</h3>
                <div class="result-item">
                    <strong>Montant initial:</strong>
                    <span>${data.amount} ${data.from_currency}</span>
                </div>
                <div class="result-item">
                    <strong>Montant converti:</strong>
                    <span class="highlight">${data_result.converted_amount} ${data.to_currency}</span>
                </div>
                <div class="result-item">
                    <strong>Taux de change:</strong>
                    <span>1 ${data.from_currency} = ${data_result.exchange_rate} ${data.to_currency}</span>
                </div>
                <div class="result-item">
                    <strong>Date:</strong>
                    <span>${new Date().toLocaleString()}</span>
                </div>
            `;
            resultDiv.style.display = 'block';
            errorDiv.style.display = 'none';
        } else {
            throw new Error(result.message || 'Erreur lors de la conversion');
        }
    } catch (error) {
        // Afficher l'erreur
        errorDiv.textContent = error.message || 'Une erreur est survenue lors de la conversion';
        errorDiv.style.display = 'block';
        resultDiv.style.display = 'none';
    } finally {
        // Réactiver le bouton
        submitButton.disabled = false;
        submitButton.innerHTML = 'Convertir';
    }
}

// Fonction de validation IBAN
async function handleIbanValidation(e) {
    e.preventDefault();

    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const resultDiv = document.getElementById('iban-result');
    const errorDiv = document.getElementById('error-message');

    // Désactiver le bouton
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="loading"></span> Validation en cours...';

    // Récupérer l'IBAN
    const iban = form.querySelector('#iban-input').value.replace(/\s/g, '');

    try {
        // TODO: Implémenter l'appel API vers /api/iban.php via IbanController
        const response = await fetch('/api/iban', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ iban: iban })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            // Afficher le résultat (adapter selon la structure de réponse du BaseController)
            const data_result = result.data || result;
            const validClass = data_result.valid ? 'success' : 'error';
            const validText = data_result.valid ? '✓ IBAN valide' : '✗ IBAN invalide';

            resultDiv.innerHTML = `
                <h3>Résultat de la validation</h3>
                <div class="result-item">
                    <strong>IBAN:</strong>
                    <span class="iban-formatted">${formatIban(iban)}</span>
                </div>
                <div class="result-item">
                    <strong>Statut:</strong>
                    <span class="${validClass}">${validText}</span>
                </div>
                ${data_result.bank_data ? `
                    <div class="result-item">
                        <strong>Banque:</strong>
                        <span>${data_result.bank_data.name || 'Non disponible'}</span>
                    </div>
                    <div class="result-item">
                        <strong>BIC:</strong>
                        <span>${data_result.bank_data.bic || 'Non disponible'}</span>
                    </div>
                    <div class="result-item">
                        <strong>Pays:</strong>
                        <span>${data_result.bank_data.country || 'Non disponible'}</span>
                    </div>
                ` : ''}
            `;
            resultDiv.style.display = 'block';
            errorDiv.style.display = 'none';
        } else {
            throw new Error(result.message || 'Erreur lors de la validation');
        }
    } catch (error) {
        // Afficher l'erreur
        errorDiv.textContent = error.message || 'Une erreur est survenue lors de la validation';
        errorDiv.style.display = 'block';
        resultDiv.style.display = 'none';
    } finally {
        // Réactiver le bouton
        submitButton.disabled = false;
        submitButton.innerHTML = 'Valider';
    }
}

// Fonction de calcul de prêt
async function handleLoanCalculation(e) {
    e.preventDefault();

    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const resultDiv = document.getElementById('loan-result');
    const errorDiv = document.getElementById('error-message');

    // Désactiver le bouton
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="loading"></span> Calcul en cours...';

    // Récupérer les données
    const formData = new FormData(form);
    const data = {
        amount: parseFloat(formData.get('amount')),
        rate: parseFloat(formData.get('rate')),
        duration: parseInt(formData.get('duration'))
    };

    try {
        // TODO: Implémenter l'appel API vers /api/loan.php via LoanController
        const response = await fetch('/api/loan', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (response.ok && result.success) {
            // Afficher le résultat (adapter selon la structure de réponse du BaseController)
            const data_result = result.data || result;
            resultDiv.innerHTML = `
                <h3>Résultat du calcul</h3>
                <div class="result-item">
                    <strong>Montant emprunté:</strong>
                    <span>${formatCurrency(data.amount)}</span>
                </div>
                <div class="result-item">
                    <strong>Taux d'intérêt:</strong>
                    <span>${data.rate}%</span>
                </div>
                <div class="result-item">
                    <strong>Durée:</strong>
                    <span>${data.duration} ans (${data_result.total_months} mois)</span>
                </div>
                <div class="result-item">
                    <strong>Mensualité:</strong>
                    <span class="highlight">${formatCurrency(data_result.monthly_payment)}</span>
                </div>
                <div class="result-item">
                    <strong>Coût total du crédit:</strong>
                    <span>${formatCurrency(data_result.total_cost)}</span>
                </div>
                <div class="result-item">
                    <strong>Total des intérêts:</strong>
                    <span>${formatCurrency(data_result.total_interest)}</span>
                </div>
            `;
            resultDiv.style.display = 'block';
            errorDiv.style.display = 'none';
        } else {
            throw new Error(result.message || 'Erreur lors du calcul');
        }
    } catch (error) {
        // Afficher l'erreur
        errorDiv.textContent = error.message || 'Une erreur est survenue lors du calcul';
        errorDiv.style.display = 'block';
        resultDiv.style.display = 'none';
    } finally {
        // Réactiver le bouton
        submitButton.disabled = false;
        submitButton.innerHTML = 'Calculer';
    }
}

// Fonctions utilitaires
function formatIban(iban) {
    return iban.replace(/(.{4})/g, '$1 ').trim();
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

// Gestion globale des erreurs
window.addEventListener('error', (e) => {
    console.error('Erreur globale:', e.error);
    const errorDiv = document.getElementById('error-message');
    if (errorDiv) {
        errorDiv.textContent = 'Une erreur inattendue est survenue. Veuillez réessayer.';
        errorDiv.style.display = 'block';
    }
});
