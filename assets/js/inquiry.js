$(function () {





    /**
     * INQUIRY INPUTS LOGIC
     */
    $('.inquiry-range input').each((index, item) => {
        item.addEventListener('blur', () => {
            console.log(item);
            let raw = item.value.replace(/\s/g, '');
            let num = parseInt(raw, 10);

            if (!isNaN(num)) {
                item.value = new Intl.NumberFormat('cs-CZ').format(num);
            } else {
                item.value = '';
            }

            let inputVal = getRawNumber(input.value);

            if (inputVal > 20000000) {
                item.value = '20 000 000';
            } else if (inputVal < 10000) {
                item.value = '10 000';
            }
        });
    });


    $('.inquiry-radio').each((index, item) => {
        let inputs = $(item).find('.ghost-radio');
        inputs.on('click', (input) => {
            $(item).find('.ghost-radio.active').removeClass('active');
            input.currentTarget.classList.add('active');
        });
    });

    $('.inquiry-checkbox').each((index, item) => {
        let inputs = $(item).find('.ghost-checkbox');
        inputs.on('click', (input) => {
            input.currentTarget.classList.toggle('active');
        });
    });


    /**
     * INQUIRY STEPS
     */
    $('.inquiry-step-btn').on('click', (input) => {
        let inputId = input.currentTarget.id;
        if (inputId === 'nextToInquiryNd' || inputId === 'backToInquiryNd') {
            document.querySelector('.inquiry-step.active').classList.remove('active');
            document.getElementById('inquiryStepNd').classList.add('active');
            document.querySelector('.progress-item.active').classList.remove('active');
            document.querySelector('.progress-item:nth-child(2)').classList.add('active');
        } else if (inputId === 'nextToInquiryRd' || inputId === 'backToInquiryRd') {
            document.querySelector('.inquiry-step.active').classList.remove('active');
            document.getElementById('inquiryStepRd').classList.add('active');
            document.querySelector('.progress-item.active').classList.remove('active');
            document.querySelector('.progress-item:nth-child(3)').classList.add('active');
        } else if (inputId === 'backToInquirySt') {
            document.querySelector('.inquiry-step.active').classList.remove('active');
            document.getElementById('inquiryStepSt').classList.add('active');
            document.querySelector('.progress-item.active').classList.remove('active');
            document.querySelector('.progress-item:nth-child(1)').classList.add('active');
        }
    });






});


function getRawNumber(input) {
    return parseInt(input.replace(/\s/g, ''), 10);
}