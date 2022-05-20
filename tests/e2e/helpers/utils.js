import API from "../api/API";
import urls from "./urls";

const timeOutTime = 2500;
const ecsterSettingsArray = {
	woocommerce_ecster_settings: {
		enabled: "yes",
		title: "Ecster",
		description: 'Pay Using Ecster - E2E',
		api_key: process.env.API_KEY,
		merchant_key: process.env.MERCHANT_KEY,
		testmode: "yes",
		logging: "yes",
		select_another_method_text: "Choose another payment method",
		manage_ecster_orders: "yes",
		customer_types: "b2cb",
	},
};

const login = async (page, username, password) => {
	await page.type("#username", username);
	await page.type("#password", password);
	await page.waitForSelector("button[name=login]");
	await page.click("button[name=login]");
};

const applyCoupons = async (page, appliedCoupons) => {
	if (appliedCoupons.length > 0) {
		await appliedCoupons.forEach(async (singleCoupon) => {
			await page.click('[class="showcoupon"]');
			await page.waitForTimeout(500);
			await page.type('[name="coupon_code"]', singleCoupon);
			await page.click('[name="apply_coupon"]');
		});
	}

	await page.waitForTimeout(timeOutTime);

	// Handle Total discount coupon error.
	let ecsterAPIErrorBanner = await page.$("#wc-ecster-api-error");
	if (ecsterAPIErrorBanner) {

		await page.waitForTimeout(timeOutTime);

		let removeCoupon = await page.$('[data-coupon="free"]');
		await removeCoupon.click();

		await page.waitForTimeout(timeOutTime);
		await page.reload()
		await page.waitForTimeout(timeOutTime);
	}

	await page.waitForTimeout(2 * timeOutTime);
};

const addSingleProductToCart = async (page, productId) => {
	const productSelector = productId;

	try {
		await page.goto(`${urls.ADD_TO_CART}${productSelector}`);
		await page.goto(urls.SHOP);
	} catch {
		// Proceed
	}
};

const addMultipleProductsToCart = async (page, products, data) => {
	const timer = products.length;

	await page.waitForTimeout(timer * 1000);
	let ids = [];

	products.forEach( name => {
		data.products.simple.forEach(product => {
			if(name === product.name) {
				ids.push(product.id);
			}
		});

		data.products.variable.forEach(product => {
			product.attribute.options.forEach(variation => {
				if(name === variation.name) {
					ids.push(variation.id);
				}
			});
		});
	});

	(async function addEachProduct() {
		for (let i = 0; i < ids.length + 1; i += 1) {
			await addSingleProductToCart(page, ids[i]);
		}
	})();

	await page.waitForTimeout(timer * 1000);
};

const setPricesIncludesTax = async (value) => {
	await API.pricesIncludeTax(value);
};

const selectEcster = async (page) => {
	if (await page.$('input[id="payment_method_ecster"]')) {
		await page.evaluate(
			(paymentMethod) => paymentMethod.click(),
			await page.$('input[id="payment_method_ecster"]')
		);
	}
}

const setOptions = async () => {
	await API.updateOptions(ecsterSettingsArray);
};

const convertEcsterTotalAmountToFloat = (totalString) => {
	return parseFloat(((totalString.split('SEK')[1]).replace(',', ''))).toFixed(2)
}

const convertWooTotalAmountToFloat = (totalString) => {
	return parseFloat((((totalString.substring(totalString.indexOf('\n') + 1)).split('kr')[0]).replace('.', '')).replace(',', '.')).toFixed(2)
}

const selectShippingMethod = async (page, shippingMethod) => {

	let searchString

	if (shippingMethod === 'free_shipping') {
		searchString = 'input[value*="free_shipping"]'
	} else if (shippingMethod === 'flat_rate') {
		searchString = 'input[value*="flat_rate"]'
	}

	let shippingMethodSelector = await page.$(searchString)

	if (shippingMethodSelector) {
		await shippingMethodSelector.focus()
		await shippingMethodSelector.click()
	}

}

export default {
	login,
	applyCoupons,
	addSingleProductToCart,
	addMultipleProductsToCart,
	setPricesIncludesTax,
	selectEcster,
	setOptions,
	convertWooTotalAmountToFloat,
	convertEcsterTotalAmountToFloat,
	selectShippingMethod

};
