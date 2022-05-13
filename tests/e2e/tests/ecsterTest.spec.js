import puppeteer from "puppeteer";
import API from "../api/API";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import iframeHandler from "../helpers/iframeHandler";
import tests from "../config/tests.json"
import data from "../config/data.json";

const options = {
	"headless": false,
	"defaultViewport": null,
	"args": [
		"--disable-infobars",
		"--disable-web-security",
		"--disable-features=IsolateOrigins,site-per-process"
	]
};

// Main selectors
let page;
let browser;
let context;
let timeOutTime = 2500;
let json = data;



describe("Ecster Checkout E2E tests", () => {
	beforeAll(async () => {
		try {
			json = await setup.setupStore(json);
			await utils.setOptions();

		} catch (e) {
			console.log(e);
		}
	}, 250000);

	beforeEach(async () => {
		browser = await puppeteer.launch(options);
		context = await browser.createIncognitoBrowserContext();
		page = await context.newPage();
	}),

		afterEach(async () => {
			if (!page.isClosed()) {
				browser.close();
			}
			API.clearWCSession();
		}),

		test.each(tests)(
			"$name",
			async (args) => {

				// --------------- GUEST/LOGGED IN --------------- //
				if (args.loggedIn) {
					await page.goto(urls.MY_ACCOUNT);
					await utils.login(page, "admin", "password");
				}

				// --------------- SETTINGS --------------- //
				await utils.setPricesIncludesTax({ value: args.inclusiveTax });
				await utils.setOptions();

				// --------------- ADD PRODUCTS TO CART --------------- //
				await utils.addMultipleProductsToCart(page, args.products, json);
				await page.waitForTimeout(timeOutTime);

				// --------------- GO TO CHECKOUT --------------- //
				await page.goto(urls.CHECKOUT);
				await page.waitForTimeout(timeOutTime);
				await utils.selectShippingMethod(page, args.shippingMethod)
				await utils.selectEcster(page);
				await page.waitForTimeout(timeOutTime);

				// --------------- COUPON HANDLER --------------- //
				await utils.applyCoupons(page, args.coupons);

				// // --------------- START OF IFRAME --------------- //
				await page.waitForTimeout(timeOutTime);
				let frameContainer = await page.$('iframe[id="ecster-pay"]')
				let ecsterIframe = await frameContainer.contentFrame();

				// --------------- CUSTOMER DATA --------------- //
				await iframeHandler.handleCustomerCredentials(page, ecsterIframe);

				// --------------- PAYMENT METHOD SELECTION --------------- //
				const ecsterOrderTotalAsFloat = await iframeHandler.paymentMethodSelector(page, ecsterIframe);

				// --------------- GET ECSTER AMOUNT ----------- //
				let ecsterTotalSansFloat = ecsterOrderTotalAsFloat.split('.')[0]
				let finalDigit = ecsterTotalSansFloat[ecsterTotalSansFloat.length - 1];

				// --------------- POST PURCHASE CHECKS --------------- //
				await page.waitForTimeout(timeOutTime);

				if ('9' == finalDigit ) {

					let errorText = await ecsterIframe.$eval(".ec-message-header", (e) => e.innerText)					
					expect(errorText).toBe('Oops, something went wrong')

				} else {
					const value = await page.$eval(".entry-title", (e) => e.textContent);
					expect(value).toBe("Order received");

					await page.waitForTimeout(timeOutTime);

					const wooOrderTotal = await page.$eval(".woocommerce-order-overview__total.total", (e) => e.innerText)
					const wooOrderTotalAsFloat = utils.convertWooTotalAmountToFloat(wooOrderTotal)

					// Get the thankyou page total and run checks.
					expect(wooOrderTotalAsFloat).toBe(ecsterOrderTotalAsFloat);

					if( args.expectedTotal !== "" ) {
						expect( parseFloat(wooOrderTotalAsFloat)).toBe(args.expectedTotal);
						expect( parseFloat(ecsterOrderTotalAsFloat)).toBe(args.expectedTotal);
					}
				}
			}, 250000);
});
