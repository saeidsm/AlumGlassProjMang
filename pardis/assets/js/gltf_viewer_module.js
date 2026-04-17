// Enhanced GLTF Viewer Module for Pardis Project
// Optimized for large models from Rhino/CAD software

class GLTFViewer {
  constructor(containerId) {
    this.container = document.getElementById(containerId);
    if (!this.container) {
      console.error("Container not found:", containerId);
      return;
    }

    this.scene = null;
    this.camera = null;
    this.renderer = null;
    this.model = null;
    this.mixer = null;
    this.clock = new THREE.Clock();
    this.animationId = null;
    this.raycaster = new THREE.Raycaster();
    this.mouse = new THREE.Vector2();

    // Camera controls state
    this.controls = {
      rotation: { x: 0.3, y: 0.3 },
      distance: 15,
      target: new THREE.Vector3(0, 0, 0),
      isDragging: false,
      previousMouse: { x: 0, y: 0 },
      mouseButton: null,
      shiftKey: false,
    };

    this.init();
  }

  init() {
    // Create scene with better background
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0xe0e0e0);
    this.scene.fog = new THREE.Fog(0xe0e0e0, 50, 200);

    // Create camera
    const width = this.container.clientWidth;
    const height = this.container.clientHeight;
    this.camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 2000);
    this.updateCameraPosition();

    // Create renderer with better settings
    this.renderer = new THREE.WebGLRenderer({
      antialias: true,
      alpha: true,
      powerPreference: "high-performance",
    });
    this.renderer.setSize(width, height);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.renderer.outputEncoding = THREE.sRGBEncoding;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.2;
    this.renderer.physicallyCorrectLights = true;
    this.container.appendChild(this.renderer.domElement);

    // Add lights
    this.setupLights();

    // Add helpers
    this.gridHelper = new THREE.GridHelper(50, 50, 0x888888, 0xcccccc);
    this.scene.add(this.gridHelper);

    this.axesHelper = new THREE.AxesHelper(10);
    this.scene.add(this.axesHelper);

    // Setup controls
    this.setupControls();

    // Handle window resize
    window.addEventListener("resize", () => this.onWindowResize());

    // Start animation loop
    this.animate();
  }

  setupLights() {
    // Brighter ambient light to show colors better
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
    this.scene.add(ambientLight);

    // Main directional light (sun) - brighter
    const mainLight = new THREE.DirectionalLight(0xffffff, 1.2);
    mainLight.position.set(10, 20, 10);
    mainLight.castShadow = true;
    mainLight.shadow.camera.left = -50;
    mainLight.shadow.camera.right = 50;
    mainLight.shadow.camera.top = 50;
    mainLight.shadow.camera.bottom = -50;
    mainLight.shadow.camera.near = 0.5;
    mainLight.shadow.camera.far = 500;
    mainLight.shadow.mapSize.width = 2048;
    mainLight.shadow.mapSize.height = 2048;
    mainLight.shadow.bias = -0.0001;
    this.scene.add(mainLight);

    // Fill light from opposite side - brighter
    const fillLight = new THREE.DirectionalLight(0xffffff, 0.6);
    fillLight.position.set(-10, 10, -10);
    this.scene.add(fillLight);

    // Additional side lights for better color visibility
    const sideLight1 = new THREE.DirectionalLight(0xffffff, 0.4);
    sideLight1.position.set(0, 5, 20);
    this.scene.add(sideLight1);

    const sideLight2 = new THREE.DirectionalLight(0xffffff, 0.4);
    sideLight2.position.set(0, 5, -20);
    this.scene.add(sideLight2);

    // Hemisphere light for natural ambient
    const hemiLight = new THREE.HemisphereLight(0xffffff, 0x888888, 0.6);
    hemiLight.position.set(0, 50, 0);
    this.scene.add(hemiLight);
  }

  setupControls() {
    const canvas = this.renderer.domElement;

    // Mouse controls
    canvas.addEventListener("mousedown", (e) => this.onMouseDown(e));
    canvas.addEventListener("mousemove", (e) => this.onMouseMove(e));
    canvas.addEventListener("mouseup", () => this.onMouseUp());
    canvas.addEventListener("wheel", (e) => this.onWheel(e), {
      passive: false,
    });
    canvas.addEventListener("mouseleave", () => this.onMouseUp());
    canvas.addEventListener("contextmenu", (e) => e.preventDefault());

    // Touch controls
    canvas.addEventListener("touchstart", (e) => this.onTouchStart(e));
    canvas.addEventListener("touchmove", (e) => this.onTouchMove(e));
    canvas.addEventListener("touchend", () => this.onTouchEnd());
  }

  onMouseDown(e) {
    this.controls.isDragging = true;
    this.controls.previousMouse = { x: e.clientX, y: e.clientY };
    this.controls.mouseButton = e.button;
    this.controls.shiftKey = e.shiftKey;
    this.renderer.domElement.style.cursor = "grabbing";

    if (e.button === 2) {
      e.preventDefault();
    }
  }

  onMouseMove(e) {
    if (!this.controls.isDragging) return;

    const deltaX = e.clientX - this.controls.previousMouse.x;
    const deltaY = e.clientY - this.controls.previousMouse.y;

    // Pan: right button OR left+shift OR middle button
    if (
      this.controls.mouseButton === 2 ||
      (this.controls.mouseButton === 0 && this.controls.shiftKey) ||
      this.controls.mouseButton === 1
    ) {
      this.pan(deltaX, deltaY);
    }
    // Rotate: left button only
    else if (this.controls.mouseButton === 0) {
      this.controls.rotation.y += deltaX * 0.005;
      this.controls.rotation.x += deltaY * 0.005;
      this.controls.rotation.x = Math.max(
        -Math.PI / 2,
        Math.min(Math.PI / 2, this.controls.rotation.x)
      );
    }

    this.updateCameraPosition();
    this.controls.previousMouse = { x: e.clientX, y: e.clientY };
  }

  onMouseUp() {
    this.controls.isDragging = false;
    this.controls.mouseButton = null;
    this.controls.shiftKey = false;
    this.renderer.domElement.style.cursor = "grab";
  }

  onWheel(e) {
    e.preventDefault();
    this.controls.distance *= 1 + e.deltaY * 0.001;
    this.controls.distance = Math.max(1, Math.min(500, this.controls.distance));
    this.updateCameraPosition();
  }

  onTouchStart(e) {
    if (e.touches.length === 1) {
      this.controls.isDragging = true;
      this.controls.previousMouse = {
        x: e.touches[0].clientX,
        y: e.touches[0].clientY,
      };
    } else if (e.touches.length === 2) {
      this.controls.previousPinchDistance = this.getTouchDistance(e.touches);
    }
  }

  onTouchMove(e) {
    e.preventDefault();

    if (e.touches.length === 1 && this.controls.isDragging) {
      const deltaX = e.touches[0].clientX - this.controls.previousMouse.x;
      const deltaY = e.touches[0].clientY - this.controls.previousMouse.y;

      this.controls.rotation.y += deltaX * 0.005;
      this.controls.rotation.x += deltaY * 0.005;
      this.controls.rotation.x = Math.max(
        -Math.PI / 2,
        Math.min(Math.PI / 2, this.controls.rotation.x)
      );

      this.updateCameraPosition();
      this.controls.previousMouse = {
        x: e.touches[0].clientX,
        y: e.touches[0].clientY,
      };
    } else if (e.touches.length === 2) {
      const currentDistance = this.getTouchDistance(e.touches);
      const delta = currentDistance - this.controls.previousPinchDistance;
      this.controls.distance *= 1 - delta * 0.01;
      this.controls.distance = Math.max(
        1,
        Math.min(500, this.controls.distance)
      );
      this.updateCameraPosition();
      this.controls.previousPinchDistance = currentDistance;
    }
  }

  onTouchEnd() {
    this.controls.isDragging = false;
    this.controls.previousPinchDistance = null;
  }

  getTouchDistance(touches) {
    const dx = touches[0].clientX - touches[1].clientX;
    const dy = touches[0].clientY - touches[1].clientY;
    return Math.sqrt(dx * dx + dy * dy);
  }

  pan(deltaX, deltaY) {
    const panSpeed = this.controls.distance * 0.001;
    const right = new THREE.Vector3();
    const up = new THREE.Vector3(0, 1, 0);

    this.camera.getWorldDirection(right);
    right.cross(up).normalize();

    this.controls.target.addScaledVector(right, -deltaX * panSpeed);
    this.controls.target.y += deltaY * panSpeed;
  }

  updateCameraPosition() {
    const { rotation, distance, target } = this.controls;

    this.camera.position.x =
      target.x + distance * Math.sin(rotation.y) * Math.cos(rotation.x);
    this.camera.position.y = target.y + distance * Math.sin(rotation.x);
    this.camera.position.z =
      target.z + distance * Math.cos(rotation.y) * Math.cos(rotation.x);

    this.camera.lookAt(target);
    this.camera.updateMatrixWorld();
  }

  fixMaterial(material, geometry) {
    const hasVertexColors = geometry && geometry.attributes.color;

    if (hasVertexColors) {
      material.vertexColors = true;
    }

    material.side = THREE.DoubleSide;
    material.flatShading = false;

    if (material.isMeshStandardMaterial || material.isMeshPhysicalMaterial) {
      material.metalness =
        material.metalness !== undefined ? material.metalness : 0.0;
      material.roughness =
        material.roughness !== undefined ? material.roughness : 0.9;
      material.envMapIntensity = 1.0;
    }

    material.needsUpdate = true;
  }

  async loadGLTF(url, onProgress) {
    this.showLoading(true, "در حال بارگذاری مدل...");

    try {
      const loader = new THREE.GLTFLoader();
      loader.setMeshoptDecoder(MeshoptDecoder);
      const dracoLoader = new THREE.DRACOLoader();
      dracoLoader.setDecoderPath("/pardis/assets/js/");
      loader.setDRACOLoader(dracoLoader);

      const gltf = await new Promise((resolve, reject) => {
        loader.load(
          url,
          (gltf) => resolve(gltf),
          (xhr) => {
            const percentComplete = (xhr.loaded / xhr.total) * 100;
            this.showLoading(
              true,
              `در حال بارگذاری: ${percentComplete.toFixed(0)}%`
            );
            if (onProgress) onProgress(percentComplete);
          },
          (error) => reject(error)
        );
      });

      this.onModelLoaded(gltf);
      this.showLoading(false);
    } catch (error) {
      console.error("Error loading GLTF:", error);
      this.showLoading(false);
      alert("خطا در بارگذاری فایل: " + (error.message || "فایل یافت نشد"));
    }
  }

  onModelLoaded(gltf) {
    if (this.model) {
      this.scene.remove(this.model);
      this.disposeObject(this.model);
    }

    this.model = gltf.scene;

    this.model.traverse((node) => {
      if (node.isMesh) {
        node.castShadow = true;
        node.receiveShadow = true;

        if (node.material) {
          const materials = Array.isArray(node.material)
            ? node.material
            : [node.material];
          materials.forEach((mat) => {
            this.fixMaterial(mat, node.geometry);
          });
        }
      }
    });

    this.centerAndFitModel();
    this.scene.add(this.model);

    if (gltf.animations && gltf.animations.length > 0) {
      this.mixer = new THREE.AnimationMixer(this.model);
      gltf.animations.forEach((clip) => {
        this.mixer.clipAction(clip).play();
      });
    }
  }

  centerAndFitModel() {
    const box = new THREE.Box3().setFromObject(this.model);
    const center = box.getCenter(new THREE.Vector3());
    const size = box.getSize(new THREE.Vector3());

    this.model.position.sub(center);
    this.controls.target.set(0, size.y / 2, 0);

    const maxDim = Math.max(size.x, size.y, size.z);
    this.controls.distance = maxDim * 1.5;

    this.camera.near = maxDim / 100;
    this.camera.far = maxDim * 10;
    this.camera.updateProjectionMatrix();

    this.updateCameraPosition();
  }

  showLoading(show, message = "در حال بارگذاری...") {
    let loader = this.container.querySelector(".gltf-loader");

    if (show) {
      if (!loader) {
        loader = document.createElement("div");
        loader.className = "gltf-loader";
        this.container.appendChild(loader);
      }
      loader.innerHTML = `<div class="gltf-spinner"></div><p>${message}</p>`;
      loader.style.display = "flex";
    } else {
      if (loader) {
        loader.style.display = "none";
      }
    }
  }

  animate() {
    this.animationId = requestAnimationFrame(() => this.animate());
    const delta = this.clock.getDelta();
    if (this.mixer) this.mixer.update(delta);
    this.renderer.render(this.scene, this.camera);
  }

  onWindowResize() {
    const width = this.container.clientWidth;
    const height = this.container.clientHeight;
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height);
  }

  resetCamera() {
    this.controls.rotation = { x: 0.3, y: 0.3 };
    if (this.model) {
      this.centerAndFitModel();
    } else {
      this.controls.distance = 15;
      this.controls.target.set(0, 0, 0);
      this.updateCameraPosition();
    }
  }

  setView(viewName) {
    if (!this.model) return;

    const box = new THREE.Box3().setFromObject(this.model);
    const size = box.getSize(new THREE.Vector3());
    const maxDim = Math.max(size.x, size.y, size.z);
    this.controls.distance = maxDim * 1.5;

    switch (viewName) {
      case "top":
        this.controls.rotation = { x: Math.PI / 2 - 0.01, y: 0 };
        break;
      case "bottom":
        this.controls.rotation = { x: -Math.PI / 2 + 0.01, y: 0 };
        break;
      case "front":
        this.controls.rotation = { x: 0, y: 0 };
        break;
      case "back":
        this.controls.rotation = { x: 0, y: Math.PI };
        break;
      case "left":
        this.controls.rotation = { x: 0, y: Math.PI / 2 };
        break;
      case "right":
        this.controls.rotation = { x: 0, y: -Math.PI / 2 };
        break;
      case "isometric":
        this.controls.rotation = { x: Math.PI / 6, y: Math.PI / 4 };
        break;
    }

    this.updateCameraPosition();
  }

  setWireframe(enabled) {
    if (!this.model) return;
    this.model.traverse((node) => {
      if (node.isMesh && node.material) {
        if (Array.isArray(node.material)) {
          node.material.forEach((mat) => (mat.wireframe = enabled));
        } else {
          node.material.wireframe = enabled;
        }
      }
    });
  }

  toggleGrid(show) {
    if (this.gridHelper) this.gridHelper.visible = show;
  }

  toggleAxes(show) {
    if (this.axesHelper) this.axesHelper.visible = show;
  }

  setExposure(value) {
    this.renderer.toneMappingExposure = value;
  }

  disposeObject(obj) {
    if (!obj) return;
    obj.traverse((child) => {
      if (child.geometry) child.geometry.dispose();
      if (child.material) {
        const materials = Array.isArray(child.material)
          ? child.material
          : [child.material];
        materials.forEach((material) => {
          Object.keys(material).forEach((prop) => {
            if (
              material[prop] &&
              typeof material[prop].dispose === "function"
            ) {
              material[prop].dispose();
            }
          });
          material.dispose();
        });
      }
    });
  }

  dispose() {
    if (this.animationId) cancelAnimationFrame(this.animationId);
    if (this.model) this.disposeObject(this.model);
    if (this.renderer) this.renderer.dispose();
  }

  takeScreenshot() {
    return this.renderer.domElement.toDataURL("image/png");
  }
}

window.GLTFViewer = GLTFViewer;
