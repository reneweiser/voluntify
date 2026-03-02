import jsQR from 'jsqr';

let animationFrameId: number | null = null;

export async function startCamera(
    video: HTMLVideoElement,
    canvas: HTMLCanvasElement,
    onDetect: (data: string) => void,
    onError?: (error: Error) => void
): Promise<void> {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });

        video.srcObject = stream;
        await video.play();

        scanLoop(video, canvas, onDetect);
    } catch (error) {
        if (onError) {
            onError(error as Error);
        }
    }
}

function scanLoop(
    video: HTMLVideoElement,
    canvas: HTMLCanvasElement,
    onDetect: (data: string) => void
): void {
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }

    const tick = () => {
        if (video.videoWidth === 0 || video.videoHeight === 0) {
            animationFrameId = requestAnimationFrame(tick);
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height);

        if (code) {
            onDetect(code.data);
        }

        animationFrameId = requestAnimationFrame(tick);
    };

    animationFrameId = requestAnimationFrame(tick);
}

export function stopCamera(video: HTMLVideoElement): void {
    if (animationFrameId !== null) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }

    const stream = video.srcObject as MediaStream | null;
    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
    }
    video.srcObject = null;
}
