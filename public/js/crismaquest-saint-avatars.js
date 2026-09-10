(() => {
    'use strict';

    // CrismaQuest saint-avatar registry.
    // IMPORTANT: faces are never generated or altered by AI here. Each avatar uses
    // an archival photograph or a historical/devotional artwork hosted on Wikimedia Commons.
    // CrismaQuest only applies the shared circular crop/frame in the interface.
    const saints = {
        'São Carlo Acutis': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/St._Carlo_Acutis.jpg',
            position: '50% 34%'
        },
        'Santa Teresinha do Menino Jesus': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Teresa-de-Lisieux.jpg',
            position: '50% 35%'
        },
        'São Francisco de Assis': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Francis_of_Assisi_-_Cimabue.jpg',
            position: '50% 28%'
        },
        'São Pedro': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Peter_A26043.jpg',
            position: '50% 28%'
        },
        'Santa Faustina Kowalska': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Faustyna_Kowalska.png',
            position: '50% 30%'
        },
        'São João Paulo II': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/JohannesPaul2-portrait.jpg',
            position: '50% 28%'
        },
        'Santa Gianna Beretta Molla': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Gianna_Beretta_Molla_(cropped).jpg',
            position: '50% 32%'
        },
        'Santo Agostinho': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Augustine_by_Philippe_de_Champaigne.jpg',
            position: '50% 28%'
        },
        'Santa Mônica': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Sainte_Monique.jpg',
            position: '50% 28%'
        },
        'São José': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Joseph_with_the_Infant_Jesus_by_Guido_Reni,_c_1635.jpg',
            position: '50% 30%'
        },
        'São Vicente de Paulo': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Anonymous_-_Portrait_de_saint_Vincent_de_Paul_(1581-1660)._-_P863_-_Musée_Carnavalet.jpg',
            position: '50% 28%'
        },
        'São Sebastião': {
            image: 'https://commons.wikimedia.org/wiki/Special:FilePath/Saint_Sebastian_painting.jpg',
            position: '50% 22%'
        }
    };

    const normalize = (value) => (value || '').replace(/\s+/g, ' ').trim();

    function installImage(avatar, saintName) {
        const config = saints[saintName];
        if (!avatar || !config || avatar.dataset.cqSaintImage === saintName) return;

        avatar.dataset.cqSaintImage = saintName;
        avatar.style.position = 'relative';
        avatar.style.overflow = 'hidden';
        avatar.style.borderRadius = '50%';
        avatar.style.background = 'linear-gradient(145deg, #f7edd6, #d9b56e)';
        avatar.style.boxShadow = '0 0 0 2px #d7b56d, 0 0 0 5px rgba(255,250,241,.92)';

        const fallbackIcon = avatar.querySelector('i');
        const img = document.createElement('img');
        img.src = config.image;
        img.alt = saintName;
        img.loading = 'lazy';
        img.decoding = 'async';
        img.referrerPolicy = 'no-referrer';
        img.style.position = 'absolute';
        img.style.inset = '0';
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        img.style.objectPosition = config.position;
        img.style.borderRadius = '50%';
        img.style.display = 'block';

        img.addEventListener('load', () => {
            if (fallbackIcon) fallbackIcon.style.visibility = 'hidden';
        }, { once: true });

        img.addEventListener('error', () => {
            img.remove();
            if (fallbackIcon) fallbackIcon.style.visibility = '';
            delete avatar.dataset.cqSaintImage;
        }, { once: true });

        avatar.appendChild(img);
    }

    function applySaintAvatars() {
        document.querySelectorAll('.cq-student-shell .cq-card').forEach((card) => {
            const heading = card.querySelector('h3');
            const saintName = normalize(heading?.textContent);
            if (!saints[saintName]) return;
            installImage(card.querySelector('.cq-avatar'), saintName);
        });

        const hero = document.querySelector('.cq-hero-content');
        if (hero) {
            const saintStrong = Array.from(hero.querySelectorAll('.cq-motto strong'))
                .find((el) => saints[normalize(el.textContent)]);
            const saintName = normalize(saintStrong?.textContent);
            if (saints[saintName]) installImage(hero.querySelector('.cq-avatar'), saintName);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applySaintAvatars, { once: true });
    } else {
        applySaintAvatars();
    }
})();
