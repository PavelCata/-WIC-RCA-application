//
const form = document.querySelector('#rca-offer-form');

if (form) {
    const customerTypeInputs = form.querySelectorAll('input[name="customer_type"]');
    const registrationType = form.querySelector('#registration_type');
    const licensePlate = form.querySelector('#license_plate');
    const vehicleIdentification = form.querySelector('#vehicle_identification_number');
    const fuelType = form.querySelector('#fuel_type');
    const engineDisplacement = form.querySelector('#engine_displacement');
    const firstRegistration = form.querySelector('#first_registration');
    const startDate = form.querySelector('[name="start_date"]');
    const yearOfConstruction = form.querySelector('[name="year_of_construction"]');
    const city = form.querySelector('#city');
    const county = form.querySelector('#county');
    const submit = form.querySelector('#offer-submit');
    const serverErrors = document.querySelector('#form-errors');
    let hasServerErrors = Boolean(serverErrors);
    const individualFields = form.querySelectorAll('[data-individual]');
    const companyFields = form.querySelectorAll('[data-company]');
    const updateCustomerFields = () => {
        const type = form.querySelector('input[name="customer_type"]:checked')?.value;
        individualFields.forEach((field) => {
            field.classList.toggle('hidden', type !== 'individual');
            const control = field.querySelector('input, select');
            control.disabled = type !== 'individual';
            control.required = type === 'individual';
        });
        companyFields.forEach((field) => {
            field.classList.toggle('hidden', type !== 'company');
            const control = field.querySelector('input, select');
            control.disabled = type !== 'company';
            control.required = control.name !== 'trade_register' && type === 'company';
        });
        form.querySelector('[data-tax-id-label]').textContent = type === 'company' ? 'CUI' : 'CNP';
        const taxId = form.querySelector('[name="tax_id"]');
        taxId.inputMode = type === 'company' ? 'text' : 'numeric';
        if (type === 'company') {
            taxId.pattern = '(RO)?[0-9]{2,10}';
        } else {
            taxId.removeAttribute('pattern');
        }
        taxId.minLength = type === 'company' ? 2 : 13;
        taxId.maxLength = type === 'company' ? 12 : 13;
    };

    const updateVehicleFields = () => {
        const registered = registrationType.value === 'registered';
        const plateRequired = ['registered', 'recorded'].includes(registrationType.value);
        licensePlate.disabled = !plateRequired;
        licensePlate.required = plateRequired;
        licensePlate.pattern = registered ? '[A-Z]{1,2}[0-9]{2,3}[A-Z]{3}' : '';
        licensePlate.value = plateRequired ? licensePlate.value : '';
        vehicleIdentification.disabled = !plateRequired;
        vehicleIdentification.required = plateRequired;
        if (vehicleIdentification.disabled) {
            vehicleIdentification.value = '';
        }
        const electric = fuelType.value === 'electric';
        engineDisplacement.disabled = electric;
        engineDisplacement.required = !electric;
        engineDisplacement.value = electric ? '0' : engineDisplacement.value;
    };

    const updateLocalities = () => {
        const localityCodes = {
            'MS:Targu Mures': '547065',
            'CJ:Cluj-Napoca': '54975',
            'B:București': '179132',
        };
        form.querySelector('#city_code').value = localityCodes[`${county.value}:${city.value}`] ?? '';
        form.querySelectorAll('#localities option').forEach((option) => {
            option.hidden = option.dataset.county !== county.value;
        });
    };

    const updateDateConstraints = () => {
        const year = yearOfConstruction.value;
        firstRegistration.min = year ? `${year}-01-01` : '';
        startDate.min = ['registered', 'recorded'].includes(registrationType.value)
            ? startDate.dataset.futureDate
            : startDate.dataset.currentDate;
    };

    const updateFormState = () => {
        submit.disabled = false;
    };

    const dismissServerErrors = () => {
        if (serverErrors) {
            serverErrors.hidden = true;
        }
        hasServerErrors = false;
    };

    customerTypeInputs.forEach((input) => input.addEventListener('change', updateCustomerFields));
    registrationType.addEventListener('change', updateVehicleFields);
    fuelType.addEventListener('change', updateVehicleFields);
    city.addEventListener('input', () => {
        updateLocalities();
        updateFormState();
    });
    county.addEventListener('change', () => {
        city.value = '';
        updateLocalities();
        updateFormState();
    });
    yearOfConstruction.addEventListener('input', updateDateConstraints);
    registrationType.addEventListener('change', updateDateConstraints);
    form.addEventListener('input', () => {
        const wasServerError = hasServerErrors;
        if (wasServerError) {
            submit.disabled = false;
        }
        dismissServerErrors();
        if (!wasServerError) {
            updateFormState();
        }
    });
    form.addEventListener('change', () => {
        const wasServerError = hasServerErrors;
        if (wasServerError) {
            submit.disabled = false;
        }
        dismissServerErrors();
        if (!wasServerError) {
            updateFormState();
        }
    });
    form.addEventListener('submit', (event) => {
        updateFormState();
        if (!form.checkValidity()) {
            event.preventDefault();
            form.querySelector(':invalid')?.focus();
        }
    });
    updateCustomerFields();
    updateVehicleFields();
    updateLocalities();
    updateDateConstraints();
    updateFormState();
}
