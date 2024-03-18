import env from './_env';

describe('OCHAServices', () => {
  beforeAll(async () => {
    await page.goto(env.baseUrl);
  });

  it('should NOT contain links with default text in the Related Platforms section', async () => {
    const relatedPlatformsText = await page.$eval('.cd-ocha-services__section :is(.cd-ocha-services__link) a', (el) => el.innerHTML);
    await expect(relatedPlatformsText).not.toMatch('Customizable');
  });

  it('should contain 10 links in the OCHA Services section', async () => {
    const otherOchaServicesLimit = 10;
    await page.waitForSelector('.cd-ocha-services__section');
    const otherOchaServicesLength = await page.$$eval('.cd-ocha-services__section', nodeList => nodeList.length);
    await expect(otherOchaServicesLength).toBeLessThanOrEqual(otherOchaServicesLimit);
  });

  it('should contain specific links in the OCHA Services section', async () => {
    const otherOchaServicesCorporate = [
      'Central Emergency Response Fund',
      'Financial Tracking Service',
      'Global Disaster Alert & Coordination System',
      'Humanitarian Data Exchange',
      'Humanitarian Action',
      'International Search and Rescue Advisory',
      'Inter-Agency Standing Committee',
      'Pooled Funds Data Hub',
      'ReliefWeb',
      'ReliefWeb Response'
    ];
    const otherOchaServicesCorporateHref = [
      'https://cerf.un.org/',
      'https://fts.unocha.org/',
      'https://gdacs.org/',
      'https://data.humdata.org/',
      'https://humanitarianaction.info/',
      'https://www.insarag.org/',
      'https://interagencystandingcommittee.org/',
      'https://pfdata.unocha.org/',
      'https://reliefweb.int/',
      'https://response.reliefweb.int/'
    ];
    const otherOchaServices = await page.$$eval('.cd-ocha-services__section .cd-ocha-services__link a', text => {
      return text.map(text => text.textContent).slice(0, 10);
    });
    const otherOchaServicesHref = await page.$$eval('.cd-ocha-services__section .cd-ocha-services__link a', anchors => {
      return anchors.map(anchor => anchor.href).slice(0, 10);
    });
    await expect(otherOchaServices).toEqual(otherOchaServicesCorporate);
    await expect(otherOchaServicesHref).toEqual(otherOchaServicesCorporateHref);
  });

  it('should include a button to UNOCHA.org DS list', async () => {
    const seeAllButtonHref = await page.$eval('.cd-ocha-services__see-all', (el) => el.href);
    const expectedUrl = 'https://www.unocha.org/ocha-digital-services';
    await expect(seeAllButtonHref).toEqual(expectedUrl);
  });
});
