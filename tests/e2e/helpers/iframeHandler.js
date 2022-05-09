import utils from "../helpers/utils";
const timeOutTime = 2500;

const handleCustomerCredentials = async (page, ecsterIframe) => {

					let personNumInputField = await ecsterIframe.$('.text-input__ctr')

					await personNumInputField.click()
					await personNumInputField.type('198010011016')

					
					await page.waitForTimeout(1000);

					let continueButton = await ecsterIframe.$('button[class="ec-button ec-bank-id ec-outline bankId ec-bankid-button"]')
					await continueButton.click()

					await page.waitForTimeout(10000)

					if(await ecsterIframe.$('input[name="contactInfo|email"]')) {
						let emailInputField = await ecsterIframe.$('input[name="contactInfo|email"]');
						await emailInputField.type('test@mail.com')
					}
					
					if (await ecsterIframe.$('input[name="contactInfo|cellular|number"]')) {
						let phoneInputField = await ecsterIframe.$('input[name="contactInfo|cellular|number"]');
						await phoneInputField.type('+46701234567')
					}

					if (await ecsterIframe.$('button[name="delivery-continue"]')) {
						let submitButton = await ecsterIframe.$('button[name="delivery-continue"]')
						await submitButton.click()
					}

					await page.waitForTimeout(1000);
}


const paymentMethodSelector = async (page, ecsterIframe) => {


	let invoiceSelector = await ecsterIframe.$('div[class="ec-expandable-panel ec-expandable-panel--no-bottom-padding section"][name="INVOICE"]');
	await invoiceSelector.click()

	await page.waitForTimeout(1000);

	const ecsterOrderTotal = await ecsterIframe.$eval('div[name="confirm-payment-value"]', (e) => e.textContent)
	const ecsterOrderTotalAsFloat = utils.convertEcsterTotalAmountToFloat(ecsterOrderTotal)

	let confirmPaymentButton = await ecsterIframe.$('button[name="confirm-payment-button"]')
	await confirmPaymentButton.click()

	return ecsterOrderTotalAsFloat;
}


const getOrderData = async (thankyouIframe) => {

	let collectorTotalAmount = await thankyouIframe.$eval("#completed-direct-invoice--output-total-amount", (e)=>{
		return (e.childNodes[0].innerHTML)
	})

	collectorTotalAmount=collectorTotalAmount.replace(/\s+/g, '');
	collectorTotalAmount=collectorTotalAmount.replace(',', '.');


	return parseFloat(collectorTotalAmount);
}

export default {
	handleCustomerCredentials,
	paymentMethodSelector,
	getOrderData,
}
