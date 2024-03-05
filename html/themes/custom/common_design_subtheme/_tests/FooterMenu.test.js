import env from './_env';

describe('FooterLinks', () => {
  beforeAll(async () => {
    await page.goto(env.baseUrl);
  });

  it('should contain specific links in the Footer menu', async () => {
    const footerMenuItems = [
      'Copyright',
      'Terms of Use',
      'Privacy'
    ];
    const footerMenuItemsHref = [
      'https://www.un.org/en/about-us/copyright',
      'https://www.un.org/en/about-us/terms-of-use',
      'https://www.un.org/en/about-us/privacy-notice'
    ];
    const footerLinks = await page.$$eval('.cd-footer .cd-footer__section--menu ul a', text => {
      return text.map(text => text.textContent);
    });
    const footerLinksHref = await page.$$eval('.cd-footer .cd-footer__section--menu ul a', anchors => {
      return anchors.map(anchor => anchor.href);
    });
    await expect(footerLinks).toEqual(expect.arrayContaining(footerMenuItems));
    await expect(footerLinksHref).toEqual(expect.arrayContaining(footerMenuItemsHref));
  });
});
