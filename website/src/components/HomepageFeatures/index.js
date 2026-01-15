import clsx from 'clsx';
import Heading from '@theme/Heading';
import styles from './styles.module.css';

const FeatureList = [
  {
    title: 'Filo Yönetimi',
    Svg: require('@site/static/img/undraw_docusaurus_mountain.svg').default,
    description: (
      <>
        Araçlarınızı detaylı özellik setleri, bakım takvimi ve fiyatlandırma
        kuralları ile tek bir merkezden yönetin.
      </>
    ),
  },
  {
    title: 'Güçlü Rezervasyon Altyapısı',
    Svg: require('@site/static/img/undraw_docusaurus_tree.svg').default,
    description: (
      <>
        Esnek rezervasyon formları, Stripe/PayPal/PayTR entegrasyonları ve
        otomatik bildirim sistemi ile işinizi kolaylaştırın.
      </>
    ),
  },
  {
    title: 'Geliştirici Dostu',
    Svg: require('@site/static/img/undraw_docusaurus_react.svg').default,
    description: (
      <>
        Genişletilebilir mimari, REST API desteği ve zengin hook/filter
        altyapısı ile projenize özelleştirin.
      </>
    ),
  },
];

function Feature({ Svg, title, description }) {
  return (
    <div className={clsx('col col--4')}>
      <div className="text--center">
        <Svg className={styles.featureSvg} role="img" />
      </div>
      <div className="text--center padding-horiz--md">
        <Heading as="h3">{title}</Heading>
        <p>{description}</p>
      </div>
    </div>
  );
}

export default function HomepageFeatures() {
  return (
    <section className={styles.features}>
      <div className="container">
        <div className="row">
          {FeatureList.map((props, idx) => (
            <Feature key={idx} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}
