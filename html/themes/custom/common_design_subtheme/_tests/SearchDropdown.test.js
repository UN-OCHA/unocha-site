import env from './_env'

describe('SearchDropdown', () => {
  beforeAll(async() => {
    await page.goto(env.baseUrl, { waitUntil: 'load' });
  });

  it('should expand when clicked', async() => {
    await page.waitForSelector('.cd-search');
    const toggle = await page.$('.cd-search__btn');
    await toggle.click();
    console.log("Search button clicked");
    const hidden = await page.$eval('.cd-search__form', el => el.dataset.cdHidden);
    await expect(hidden).toMatch('false');
  });
});
