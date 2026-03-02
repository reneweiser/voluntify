import { describe, it, expect, vi, beforeEach } from 'vitest';
import { startCamera, stopCamera } from '@/scanner/camera';

describe('camera', () => {
    let mockVideo: HTMLVideoElement;
    let mockCanvas: HTMLCanvasElement;
    let mockStream: MediaStream;
    let stopTrack: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        stopTrack = vi.fn();
        mockStream = {
            getTracks: () => [{ stop: stopTrack }],
        } as unknown as MediaStream;

        mockVideo = {
            srcObject: null,
            play: vi.fn().mockResolvedValue(undefined),
            videoWidth: 640,
            videoHeight: 480,
        } as unknown as HTMLVideoElement;

        mockCanvas = {
            getContext: vi.fn().mockReturnValue({
                drawImage: vi.fn(),
                getImageData: vi.fn().mockReturnValue({
                    data: new Uint8ClampedArray(640 * 480 * 4),
                    width: 640,
                    height: 480,
                }),
            }),
            width: 0,
            height: 0,
        } as unknown as HTMLCanvasElement;
    });

    it('requests rear camera', async () => {
        const mockGetUserMedia = vi.fn().mockResolvedValue(mockStream);
        vi.stubGlobal('navigator', {
            mediaDevices: { getUserMedia: mockGetUserMedia },
        });

        await startCamera(mockVideo, mockCanvas, vi.fn());

        expect(mockGetUserMedia).toHaveBeenCalledWith({
            video: { facingMode: { ideal: 'environment' } },
        });

        stopCamera(mockVideo);
        vi.unstubAllGlobals();
    });

    it('handles permission denied', async () => {
        const mockGetUserMedia = vi.fn().mockRejectedValue(new DOMException('Permission denied', 'NotAllowedError'));
        vi.stubGlobal('navigator', {
            mediaDevices: { getUserMedia: mockGetUserMedia },
        });

        const onError = vi.fn();
        await startCamera(mockVideo, mockCanvas, vi.fn(), onError);

        expect(onError).toHaveBeenCalledWith(expect.objectContaining({ name: 'NotAllowedError' }));

        vi.unstubAllGlobals();
    });

    it('stops all tracks on stopCamera', async () => {
        const mockGetUserMedia = vi.fn().mockResolvedValue(mockStream);
        vi.stubGlobal('navigator', {
            mediaDevices: { getUserMedia: mockGetUserMedia },
        });

        await startCamera(mockVideo, mockCanvas, vi.fn());
        stopCamera(mockVideo);

        expect(stopTrack).toHaveBeenCalled();
        expect(mockVideo.srcObject).toBeNull();

        vi.unstubAllGlobals();
    });
});
