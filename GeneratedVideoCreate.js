import React, { useRef, useMemo, useCallback, useState, useEffect } from 'react';
import MainLayout from '../../layouts/Main';
import routes, { getDynamicRoute } from '../../constants/routes';
import { useHistory } from 'react-router-dom';
import { shallowEqual, useDispatch, useSelector } from 'react-redux';
import { set, useForm, Controller } from "react-hook-form";
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from "yup";
import ErrorMessage from '../../components/base/ErrorMessage';
import ApiIndex from '../../api';
import toast from 'react-hot-toast';
import { Button, Spinner } from 'evergreen-ui';
import VideoThumbnail from 'react-video-thumbnail';
import PreviewAvatarVideoModal from '../../components/modal/generateVideo/PreviewAvatarVideoModal';
import ReactAudioPlayer from 'react-audio-player';
import _ from 'lodash';
import { useErrorMessage } from '../../hooks';

let createVideoSchema = yup.object().shape({
    title: yup.string().max(100).required('A title is required'),
    description: yup.string().max(100).required('A description is required'),
    transcript: yup.string().max(1000).required('Transcript of the videos is required'),
    ctaLabel: yup.string().required('Call to action label is required'),
    ctaUrl: yup.string().url().required('Call to action URL is required'),
    horizontalAlign: yup.string().required('Horizontal alignment of avatar is required').when("style", {
        is: 'circular',
        then: yup.string().required("Horizontal alignment of avatar is required").equals(['none'], 'Horizontal alignment should be none when style is circular')
    }),
    style: yup.string().required('Avatar style is required'),
    voice: yup.string().required('Avatar voice is required'),
    soundTrack: yup.string().required('Sound Track is required'),
    background: yup.string().required('Background is required'),
    short_content_match_mode: yup.string().required('Video Background Mode is required'),
    long_content_match_mode: yup.string().required('Video Background Mode is required'),
});


const VideoCreate = () => {

    const history = useHistory();
    const { handleErrors, clearErrors, renderErrors } = useErrorMessage();
    const [avatars, setAvatars] = useState([]);
    const [soundTracks, setSoundTracks] = useState([]);
    const [avatarVoices, setAvatarVoices] = useState([]);
    const [initialized, setInitialized] = useState(false);
    const [isPreviewModalOpen, setPreviewModalOpen] = useState(false);
    const [previewAvatar, setPreviewAvatar] = useState(false);
    const [selectedAvatar, setSelectedAvatar] = useState(undefined);

    const [requestLoading, setRequestLoading] = useState(false);
    const [errorMessage, setErrorMessage] = useState(undefined);

    const { register, handleSubmit, control, formState: { errors } } = useForm({
        resolver: yupResolver(createVideoSchema)
    });

    const breadCrumbs = useMemo(() => {
        return [
            {
                name: 'Dashboard',
                link: routes.DASHBOARD
            },
            {
                name: 'AI Presenter',
                link: routes.GENERATED_VIDEO_INDEX
            },
            {
                name: 'Create Video',
                link: routes.GENERATED_VIDEO_CREATE
            },
        ]
    }, []);

    const getAvatars = async () => {
        try {
            setInitialized(false);
            const response = await ApiIndex.GeneratedVideoApi.getAvatars();
            setAvatars(response.data.data);
            setInitialized(true);
        }
        catch (error) {

        }
    }

    const getSoundTracks = async () => {
        try {
            setInitialized(false);
            const response = await ApiIndex.GeneratedVideoApi.getSoundTracks();
            setSoundTracks(response.data.data);
            console.log(soundTracks);
            setInitialized(true);
        }
        catch (error) {
            console.log(error)
        }
    }

    useEffect(() => {
        getAvatars();
        getSoundTracks();
    }, []);

    const handleCreateVideo = async (data) => {
        clearErrors();
        setRequestLoading(true);
        setErrorMessage(undefined);

        if(!selectedAvatar)
        {
            throw new Error('Please select an avatar');
        }

        const avatar = avatars.find((item) => item.id === selectedAvatar);

        data = {
            "transcript": data.transcript,
            "title": data.title,
            "options": {
                "test": true,
                "title": data.title,
                "description": data.description,
                "label": data.ctaLabel,
                "url": data.ctaUrl,
                "scriptText": data.transcript,
                "avatar": avatar.actor_id,
                "voice": data.voice,
                "horizontalAlign": data.horizontalAlign,
                "scale": 1.0,
                "style": data.style,
                "backgroundColor": "#F2F7FF",
                "seamless": false,
                "background": data.background,
                "shortBackgroundContentMatchMode": data.short_content_match_mode,
                "longBackgroundContentMatchMode": data.long_content_match_mode,
                "soundtrack": data.soundTrack
            }
        };

        try {
            const response = await ApiIndex.GeneratedVideoApi.createVideo(data);
            setRequestLoading(false);
            console.log(response);
            history.push(routes.GENERATED_VIDEO_INDEX);
            toast.success('Ai presenter video generation in progress');

        }
        catch (error) {
            handleErrors(error);

            setRequestLoading(false);
            toast.error('Failed to create AI presenter video');
        }
    }


    const openFormModal = (previewAvatar) => {
        setPreviewModalOpen(true);
        setPreviewAvatar(previewAvatar);
        getSoundTracks();
    }


    const closeFormModal = () => {
        setPreviewModalOpen(false);
    }

    const handleSelectAvatar = async (avatar) => {
        setSelectedAvatar(avatar.id);
        const response = await ApiIndex.GeneratedVideoApi.getAvatarVoicesByGender(avatar.gender);
        setAvatarVoices(response.data.data);
    }


    return (
        <MainLayout content={
            <>
                <div>
                    <div className="md:grid md:grid-cols-6 mb-5">
                        <div className="mt-5 md:mt-0 md:col-span-4 md:col-start-2">
                            <form>
                                <div className="shadow sm:rounded-md sm:overflow-hidden">
                                    <div className="px-4 py-3 bg-gray-50 text-left sm:px-6 font-semibold">
                                        <h1 className='text-lg'>Generate Video</h1>
                                    </div>
                                    <div className="px-4 py-5 bg-white space-y-6 sm:p-6">
                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Video Title
                                                </label>
                                                <input
                                                    {...register("title")}
                                                    type="text"
                                                    placeholder='Title of the generated video'
                                                    className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                />
                                            </div>
                                            {
                                                errors.title &&
                                                <ErrorMessage message={errors.title.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <label htmlFor="about" className="block text-sm font-medium text-gray-700">
                                                Video Description
                                            </label>
                                            <div className="mt-1">
                                                <textarea
                                                    {...register("description")}
                                                    rows={2}
                                                    className="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md"
                                                    placeholder="Description of the generated video"
                                                    defaultValue={''}
                                                />
                                            </div>
                                            {
                                                errors.description &&
                                                <ErrorMessage message={errors.description.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <label htmlFor="about" className="block text-sm font-medium text-gray-700">
                                                Video Script
                                            </label>
                                            <div className="mt-1">
                                                <textarea
                                                    {...register("transcript")}
                                                    rows={4}
                                                    className="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md"
                                                    placeholder="What you want the AI presenter to say"
                                                    defaultValue={''}
                                                />
                                            </div>
                                            {
                                                errors.transcript &&
                                                <ErrorMessage message={errors.transcript.message} />
                                            }
                                        </div>

                                        <div >
                                            <div>
                                                <div tabindex="0" className="collapse border rounded-box border-base-300 collapse-arrow">
                                                    <input type="checkbox" />
                                                    <div className="collapse-title text-center flex flex-col">
                                                        <p className="text-xl font-medium">Select Your Avatar</p>
                                                        <p className="text-sm">The avatar that you select will affect the language and voice options available</p>
                                                    </div>
                                                    <div className="collapse-content bg-white">
                                                        <div className='md:grid md:grid-cols-4 md:gap-4 grid grid-cols-3 gap-4'>
                                                            {
                                                                avatars.map((avatar, index) => (
                                                                    <AvatarBox key={index} avatar={avatar} isActive={selectedAvatar == avatar.id} onPreview={openFormModal} onSelect={handleSelectAvatar} />
                                                                ))
                                                            }
                                                        </div>
                                                    </div>

                                                </div>
                                                {/* {
                                                    !isAvatarSelected &&
                                                    <ErrorMessage message="Avatar Should be Selected" />
                                                } */}
                                            </div>
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Supported Avatar Voices
                                                </label>
                                                <select
                                                    {...register("voice")}
                                                    className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                >

                                                    {
                                                        avatarVoices.map((voice) => (
                                                            <option value={voice.voice_id}>{voice.language} - {voice.name}</option>
                                                        ))
                                                    }
                                                </select>
                                            </div>
                                            {
                                                errors.voice &&
                                                <ErrorMessage message={errors.voice.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Call To Action Label
                                                </label>
                                                <input
                                                    {...register("ctaLabel")}
                                                    type="text"
                                                    placeholder='Add CTA Label'
                                                    className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                />
                                            </div>
                                            {
                                                errors.ctaLabel &&
                                                <ErrorMessage message={errors.ctaLabel.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Call To Action URL
                                                </label>
                                                <input
                                                    {...register("ctaUrl")}
                                                    type="text"
                                                    placeholder='Add CTA URL'
                                                    className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                />
                                            </div>
                                            {
                                                errors.ctaUrl &&
                                                <ErrorMessage message={errors.ctaUrl.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Avatar Horrizontal Align
                                                </label>
                                                <select
                                                    {...register("horizontalAlign")}
                                                    className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                >
                                                    <option value="center">Center</option>
                                                    <option value="left">Left</option>
                                                    <option value="right">Right</option>
                                                    <option value="none">None</option>
                                                </select>
                                            </div>
                                            {
                                                errors.horizontalAlign &&
                                                <ErrorMessage message={errors.horizontalAlign.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Avatar Style
                                                </label>
                                                <select
                                                    {...register("style")}
                                                    className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                >
                                                    <option value="rectangular">Rectangular</option>
                                                    <option value="circular">Circular</option>
                                                </select>
                                            </div>
                                            {
                                                errors.style &&
                                                <ErrorMessage message={errors.style.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Sound Tracks
                                                </label>
                                                <br />
                                                <div className='grid grid-cols-2 gap-4'>
                                                    {
                                                        soundTracks.map((soundTrack) => (
                                                            <div className="flex flex-col gap-3">
                                                                <div className="flex gap-2 justify-start items-center">
                                                                    <input
                                                                        {...register("soundTrack")}
                                                                        type="radio"
                                                                        className='cursor-pointer'
                                                                        value={soundTrack.name}
                                                                    /> 
                                                                    {_.capitalize(soundTrack.name)}
                                                                </div>

                                                                <ReactAudioPlayer src={soundTrack.path}
                                                                    width={20}
                                                                    controls />
                                                            </div>
                                                        ))

                                                    }
                                                </div>
                                            </div>
                                            {
                                                errors.soundTrack &&
                                                <ErrorMessage message={errors.soundTrack.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Stock Background
                                                </label>
                                                <select
                                                    {...register("background")}
                                                    className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                >
                                                    <option value="off_white">Off White</option>
                                                    <option value="warm_white">Warm White</option>
                                                    <option value="light_pink">Light Pink</option>
                                                    <option value="soft_pink">Soft Pink</option>
                                                    <option value="light_blue">Light Blue</option>
                                                    <option value="dark_blue">Dark Blue</option>
                                                    <option value="soft_cyan">Soft Cyan</option>
                                                    <option value="strong_cyan">Strong Cyan</option>
                                                    <option value="light_orange">Light Orange</option>
                                                    <option value="soft_orange">Soft Orange</option>
                                                    <option value="white_studio">White Studio</option>
                                                    <option value="white_cafe">White Cafe</option>
                                                    <option value="luxury_lobby">Luxury Lobby</option>
                                                    <option value="large_window">Large Window</option>
                                                    <option value="white_meeting_room">White Meeting Room</option>
                                                    <option value="open_office">Open Office</option>

                                                </select>
                                            </div>
                                            {
                                                errors.background &&
                                                <ErrorMessage message={errors.background.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Video Short Background Content Match Mode
                                                </label>
                                                <select
                                                    {...register("short_content_match_mode")}
                                                    className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                >
                                                    <option value="freeze">Freeze</option>
                                                    <option value="loop">Loop</option>
                                                    <option value="slow_down">Slow Down</option>
                                                </select>
                                            </div>
                                            {
                                                errors.short_content_match_mode &&
                                                <ErrorMessage message={errors.short_content_match_mode.message} />
                                            }
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex flex-col">
                                                <label htmlFor="first-name" className="block text-sm font-medium text-gray-700">
                                                    Video Long Background Content Match Mode
                                                </label>
                                                <select
                                                    {...register("long_content_match_mode")}
                                                    className="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                >
                                                    <option value="trim">Trim</option>
                                                    <option value="speed_up">Speed Up</option>
                                                </select>
                                            </div>
                                            {
                                                errors.long_content_match_mode &&
                                                <ErrorMessage message={errors.long_content_match_mode.message} />
                                            }
                                        </div>


                                        {renderErrors()}
                                    </div>
                                    <div className="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                        <Button
                                            isLoading={requestLoading}
                                            onClick={handleSubmit(handleCreateVideo)}
                                            className='focus:outline-none'
                                            appearance='primary'                                        >
                                            Create
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div>
                    {
                        isPreviewModalOpen &&
                        <PreviewAvatarVideoModal visible={isPreviewModalOpen} avatar={previewAvatar} onClose={() => closeFormModal()} />
                    }
                </div>
            </>
        } breadCrumbs={breadCrumbs} />
    );
}

export default VideoCreate;


const AvatarBox = ({ avatar, isActive, onSelect, onPreview }) => {

    const [isHovered, setHovered] = useState(false);
    const [isLoading, setLoading] = useState(true);

    const handleThumbnailUrl = () => {
        setLoading(false);
    }

    return (
        <div className={`w-full relative hover:scale-105 transform transition-transform ease-linear ${isActive && 'border-4 border-blue-600'}`} onMouseEnter={() => setHovered(true)} onMouseLeave={() => setHovered(false)}>
            <div>
                <div className='relative rounded-xl shadow-xl cursor-pointer'>

                    {
                        (isHovered && !isLoading) &&
                        <div className='flex justify-center items-center p-4 absolute object-center gap-2 h-full w-full px-4 py-2 rounded' >
                            <svg className="h-8 w-8 text-black" className="w-8 h-8 cursor-pointer transition duration-500 hover:scale-105 bg-white bg-opacity-50 rounded-lg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" strokeLinecap="round" strokeLinejoin="round"
                                onClick={() => onPreview(avatar)} >
                                <path stroke="none" d="M0 0h24v24H0z" />
                                <circle cx="12" cy="12" r="2" />
                                <path d="M2 12l1.5 2a11 11 0 0 0 17 0l1.5 -2" />
                                <path d="M2 12l1.5 -2a11 11 0 0 1 17 0l1.5 2" />
                            </svg>

                            <svg onClick={() => onSelect(avatar)} xmlns="http://www.w3.org/2000/svg" className="h-8 w-8 text-white" viewBox="0 0 20 20" className="w-8 h-8 cursor-pointer hover:scale-105 bg-white bg-opacity-50 rounded-lg " fill="currentColor" >
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    }
                    {
                        isLoading &&
                        <div className='flex justify-center items-center p-4 absolute object-center gap-2 h-full w-full'>
                            <Spinner size={30} />
                        </div>
                    }

                    <div className={isLoading ? 'opacity-0' : 'opacity-100'}>
                        <VideoThumbnail
                            thumbnailHandler={handleThumbnailUrl}
                            className='rounded-xl'
                            videoUrl={avatar.video_url}
                        />
                    </div>

                </div>
            </div>
        </div >
    )
}