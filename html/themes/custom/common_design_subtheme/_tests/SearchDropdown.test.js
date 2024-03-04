import env from './_env'

describe('SearchDropdown', () => {
  beforeAll(async() => {
    await page.goto(env.baseUrl);
  });

  it('should expand when clicked', async() => {
    let toggle = '.cd-search__btn';
    await page.waitForSelector(toggle, {timeout: 3000});
    await page.click(toggle);
    const hidden = await page.$eval('.cd-search__form', el => el.dataset.cdHidden);
    await expect(hidden).toMatch('false');
  });
});
