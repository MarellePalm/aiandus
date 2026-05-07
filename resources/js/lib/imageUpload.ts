const DEFAULT_MAX_BYTES = 4_500_000;
const DEFAULT_MAX_WIDTH = 2200;
const DEFAULT_MAX_HEIGHT = 2200;
const DEFAULT_OUTPUT_TYPE = 'image/webp';

type NormalizeImageOptions = {
    maxBytes?: number;
    maxWidth?: number;
    maxHeight?: number;
    outputType?: string;
};

function loadImage(file: File): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(file);

        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Pildi laadimine ebaõnnestus.'));
        };

        image.src = objectUrl;
    });
}

function scaleToFit(width: number, height: number, maxWidth: number, maxHeight: number) {
    const ratio = Math.min(maxWidth / width, maxHeight / height, 1);
    return {
        width: Math.max(1, Math.round(width * ratio)),
        height: Math.max(1, Math.round(height * ratio)),
    };
}

function canvasToBlob(
    canvas: HTMLCanvasElement,
    type: string,
    quality: number,
): Promise<Blob> {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Pildi teisendamine ebaõnnestus.'));
                    return;
                }
                resolve(blob);
            },
            type,
            quality,
        );
    });
}

export async function normalizeImageForUpload(
    file: File,
    options: NormalizeImageOptions = {},
): Promise<File> {
    const {
        maxBytes = DEFAULT_MAX_BYTES,
        maxWidth = DEFAULT_MAX_WIDTH,
        maxHeight = DEFAULT_MAX_HEIGHT,
        outputType = DEFAULT_OUTPUT_TYPE,
    } = options;

    if (!file.type.startsWith('image/')) return file;

    const image = await loadImage(file);
    const { width, height } = scaleToFit(
        image.naturalWidth,
        image.naturalHeight,
        maxWidth,
        maxHeight,
    );

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    if (!context) return file;

    context.drawImage(image, 0, 0, width, height);

    const qualitySteps = [0.9, 0.82, 0.74, 0.66, 0.58, 0.5, 0.42, 0.34];
    let bestBlob: Blob | null = null;

    for (const quality of qualitySteps) {
        const blob = await canvasToBlob(canvas, outputType, quality);
        bestBlob = blob;
        if (blob.size <= maxBytes) break;
    }

    if (!bestBlob) return file;
    if (bestBlob.size >= file.size && file.size <= maxBytes) return file;

    const ext = outputType === 'image/png' ? 'png' : outputType === 'image/webp' ? 'webp' : 'jpg';
    const safeName = file.name.replace(/\.[^.]+$/, '') || 'photo';
    return new File([bestBlob], `${safeName}.${ext}`, { type: outputType });
}
